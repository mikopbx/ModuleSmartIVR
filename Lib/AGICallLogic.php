<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 9 2020
 */

namespace Modules\ModuleSmartIVR\Lib;

use MikoPBX\Common\Models\CallDetailRecords;
use MikoPBX\Core\Asterisk\AGI;
use MikoPBX\Modules\PbxExtensionBase;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\Users;
use MikoPBX\Core\System\MikoPBXConfig;
use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleSmartIVR\Models\ModuleSmartIVR;



/**
 * Class SmartIVR
 */
class AGICallLogic extends PbxExtensionBase
{
    public string $ttsDir = '';
    /** @var ?|AGI $agi */
    private ?AGI $agi = null;
    private $internalExtLength = 3;
    private string $contextInternal = 'internal';
    private string $number;
    private array $messages = [];
    private WebService1C $web_service_1C;
    private $count_of_repeat_ivr;
    private $timeout_extension;
    private $failover_extension;
    private $module_extension;
    private bool $notUse1c;

    private $last_responsible_duration;
    private $last_responsible_time;

    /**
     * SmartIVR constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->number = '74952293042'; //  Для тестирования, норм потом в AGI он переопределиться

        // Каталог генерированных файлов.
        $this->ttsDir = $this->config->path('asterisk.mediadir') . '/text2speech/';
        Util::mwMkdir($this->ttsDir);

        // Получим настройки подключения к 1С.
        $settings = ModuleSmartIVR::findFirst();
        if ( ! $settings) {
            $this->messages[] = 'Settings not found.';
            $this->logger->writeError('Module settings not found');
        } else {
            $this->module_extension   = $settings->extension;
            $this->timeout_extension  = $settings->timeout_extension;
            $this->failover_extension = $settings->failover_extension;
            $mikoPbxConfig            = new MikoPBXConfig();
            $this->internalExtLength  = $mikoPbxConfig->getGeneralSettings('PBXInternalExtensionLength');
            // Количество повторов меню перед переводом на резервный номер
            $this->count_of_repeat_ivr = 3;
            if (isset($settings->number_of_repeat)
                && $settings->number_of_repeat > 0) {
                $this->count_of_repeat_ivr = $settings->number_of_repeat;
            }
            $params1C = [
                'database'       => $settings->database,
                'login'          => $settings->login,
                'secret'         => $settings->secret,
                'server_1c_host' => $settings->server1chost,
                'server_1c_port' => $settings->server1cport,
                'library_1c'     => $settings->library_1c,
                'use_ssl'        => $settings->useSSL === '1',
                'logger'         => $this->logger,
            ];

            $this->notUse1c = $settings->server1chost === '0.0.0.0';
            $this->web_service_1C            = new WebService1C($params1C);
            $this->logger->debug             = $settings->debug_mode === '1';
            $this->last_responsible_time     = $settings->last_responsible_time;
            $this->last_responsible_duration = ($settings->last_responsible_duration <=0)?30:$settings->last_responsible_duration;
        }
    }

    /**
     * Получать номер телефона сотруника, кто последний говори с клиентом.
     * @param string $number
     * @return string
     */
    private function getLastResponsibleNumber(string $number):string{
        $responsibleNumber = '';
        if($this->last_responsible_time <= 0){
            return $responsibleNumber;
        }
        // Вычислим дату начала анализа звонков.
        $time = date("Y-m-d H:i:s.v", time()-60*$this->last_responsible_time);
        $innerNumbers = [];

        // Получим внутренние номера SIP.
        $extensions = Extensions::find('type="SIP" OR type="EXTERNAL"')->toArray();
        foreach ($extensions as $data){
            $innerNumbers[] = substr($data['number'], -10);
        }

        // Получим номер телефона сотрудника, с кем последним говорил клиент.
        $filter=[
            'conditions' => "src_num=:src_num: AND start>:data_time:",
            'order' => 'start desc',
            'bind'       => [
                'src_num'   => $number,
                'data_time' => $time,
            ]
        ];

        $cdr = CallDetailRecords::find($filter)->toArray();
        foreach ($cdr as $row){
            $dst_num = substr($row['dst_num'], -10);
            if(in_array($dst_num, $innerNumbers, true)){
                $this->Verbose("Find responsible number {$responsibleNumber}. ID='{$row['linkedid']}', Date='{$row['start']}'");
                $status = $this->getExtensionStatus($row['dst_num']);
                if ($status === -1) {
                    $this->Verbose("Responsible {$responsibleNumber} -> extension UNKNOWN ($status)");
                } elseif ($status === 2 || $status === 1) {
                    $this->Verbose("Responsible {$responsibleNumber} -> extension BUSY ($status)");
                }else{
                    $responsibleNumber = $row['dst_num'];
                }
                break;
            }
        }

        return $responsibleNumber;
    }

    /**
     * Начало работы IVR.
     */
    public function startIVR(): void
    {
        $this->agi    = new AGI();

        $did          = $this->agi->get_variable('FROM_DID', true);
        $this->number = $this->agi->request['agi_callerid'];
        if(empty($did) && mb_strlen($this->number) <= 4){
            $this->agi->verbose('Use 777777777 as caller id...', 3);
            // Это тестовый внутренний вызов;
            $this->number = '777777777';
        }
        $this->logger->writeInfo("Preparing IVR for {$this->number}");
        $DialStatus = '';
        $this->agi->exec('Ringing', '');
        $this->agi->set_variable('AGIEXITONHANGUP', 'yes');
        $this->agi->set_variable('AGISIGHUP', 'yes');
        $this->agi->set_variable('__ENDCALLONANSWER', 'yes');

        $responsibleNumber = $this->getLastResponsibleNumber($this->number);
        if(!empty($responsibleNumber)){
            $this->agi->set_variable('__pt1c_UNIQUEID', '');
            $this->agi->exec(
                'Dial',
                "Local/{$responsibleNumber}@{$this->contextInternal}/n,{$this->last_responsible_duration}," . 'TtekKHhU(dial_answer)b(dial_create_chan,s,1)'
            );
            $DialStatus = $this->getDialStatus();
            $this->Verbose("Dial status after connect ({$DialStatus}).");
            if ('ANSWER' === strtoupper($DialStatus)) {
                $this->Verbose('Call answered the script sends HANGUP command to PBX');
                $this->agi->hangup();
                return;
            }
            $this->Verbose('Call did not answer continue the IVR logic');
        }
        if($this->notUse1c){
            return;
        }

        $ivr_menu_text = $this->web_service_1C->getIvrMenuText($this->number);
        $ivr_menu_file = null;
        if ($ivr_menu_text !== null) {
            $ivr_menu_file = $this->text2speech($ivr_menu_text);
        }
        $this->agi->Answer();
        if ($ivr_menu_file === null) {
            $this->Verbose("Error - redirect to failover extension {$this->failover_extension}");
            $this->endWork($DialStatus, $this->failover_extension);

            return;
        }

        for ($i = 0; $i < $this->count_of_repeat_ivr; $i++) {
            $this->Verbose('Said -> IVR menu text message from CRM');
            $result      = $this->agi->getData($ivr_menu_file, 3000, $this->internalExtLength);
            $selectednum = $result['result'];
            if (empty($selectednum)) {
                continue;
            }
            $this->Verbose("Set extension - $selectednum -");
            $status = $this->getExtensionStatus($selectednum);
            if ($status === -1) {
                $this->Verbose('Said -> extension UNKNOWN');
                $text     = $this->getIVRPhrase('UnknownNumber');
                $filename = $this->text2speech([urlencode($text)]);
                $this->agi->stream_file($filename);
                $this->count_of_repeat_ivr++;
            } elseif ($status === 2 || $status === 1) {
                $this->Verbose('Said -> extension busy');
                $text = $this->getIVRPhrase('ExtensionBusy');
                $file = $this->text2speech([urlencode($text)]);
                $this->agi->stream_file($file);
                $this->count_of_repeat_ivr++;
            } else {
                $this->Verbose('Said -> connect to extension');
                $text     = $this->getIVRPhrase('ConnectionToExtension', $selectednum);
                $filename = $this->text2speech([urlencode($text)]);
                $this->agi->stream_file($filename);

                $this->agi->set_variable('__pt1c_UNIQUEID', '');
                $this->agi->exec(
                    'Dial',
                    "Local/{$selectednum}@{$this->contextInternal}/n,30," . 'TtekKHhU(dial_answer)b(dial_create_chan,s,1)'
                );

                $DialStatus = $this->getDialStatus();
                $this->Verbose("Dial status after connect ({$DialStatus}).");
                if ('ANSWER' === strtoupper($DialStatus)) {
                    $this->Verbose('Call answered the script sends HANGUP command to PBX');
                    $this->agi->hangup();

                    return;
                } else {
                    $this->Verbose('Call did not answer continue the IVR logic');
                }
                $text     = $this->getIVRPhrase('TryAgain');
                $filename = $this->text2speech([urlencode($text)]);
                $this->agi->stream_file($filename);
                $this->count_of_repeat_ivr++;
            }
        }
        $this->endWork($DialStatus, $this->timeout_extension);
    }

    /**
     * Генерация речи универсальная, в зависимости от выбранного сервиса TTS запускает нужную функцию.
     *
     * @param      $arr_text_to_speech
     *
     * @return null|string
     */
    private function text2speech($arr_text_to_speech): ?string
    {
        $ttsSettings = $this->web_service_1C->getTTSSettings();
        if ($ttsSettings === null) {
            $this->Verbose('Error on get TTS settings from CRM');

            return null;
        }
        $settings = [
            'ttsDir' => $this->ttsDir,
            'auth'   => $ttsSettings->auth,
            'logger' => $this->logger,
        ];

        switch ($ttsSettings->tts_service) {
            case 'Yandex':
            {
                $tts = new YandexTTS($settings);
                break;
            }
            case 'CRT':
            {
                $tts = new SpeechProTTS($settings);
                break;
            }
            default:
                $this->Verbose("Unknown TTS service: $ttsSettings->tts_service");

                return null;
        }
        $result = $tts->Synthesize($arr_text_to_speech, $ttsSettings->dictor);
        if (empty($result)) {
            $messages = $tts->getMessages();
            foreach ($messages as $message) {
                $this->Verbose($message);
            }
            $result = null;
        }

        return $result;
    }

    /**
     * Вывести сообщение об отладке
     *
     * @param $value
     */
    public function Verbose($value): void
    {
        $this->messages[] = $value;
        if ($this->agi !== null) {
            $this->agi->verbose('SMART IVR VERBOSE: ' . escapeshellarg($value), 3);
        }
    }

    /**
     * Обработка завершения работы скрипта.
     *
     * @param string $DialStatus
     * @param string $extension - номер куда отправим звонок после завершения работы скрипта
     */
    private function endWork($DialStatus, $extension): void
    {
        $this->Verbose('Return the IVR logic');
        // Переадресация на резервный номер.
        $DialStatus = strtoupper($DialStatus);
        $state      = $this->getExtensionStatus($extension);
        if ('ANSWER' !== $DialStatus && $state !== -1) {
            $this->Verbose("Redirect call to the default route ({$extension}).");
            $this->agi->exec_goto($this->contextInternal, (string)$extension, '1');
        } else {
            $this->Verbose("Call answered the script sends HANGUP command to PBX. State $state | Exten $extension");
            $this->agi->hangup();
        }
    }

    /**
     * Проверяет, существует ли добавочный номер в системе и возвращает его статус.
     *
     * @param        $number
     *
     * @return int
     */
    private function getExtensionStatus($number): int
    {
        $state   = $this->agi->get_variable("DEVICE_STATE(PJSIP/$number)", true);
        $dExists = $this->agi->get_variable("DIALPLAN_EXISTS(internal,$number,1)", true);
        $this->Verbose("DEVICE_STATE: {$state} DIALPLAN_EXISTS: $dExists");

        $stateTable = [
            'UNKNOWN'       => ['Status'=> -1, 'StatusText' => 'Unknown'],
            'INVALID'       => ['Status'=> -1, 'StatusText' => 'Unknown'],
            'NOT_INUSE'     => ['Status'=> 0, 'StatusText' => 'Idle'],
            'INUSE'         => ['Status'=> 1, 'StatusText' => 'In Use'],
            'BUSY'          => ['Status'=> 2, 'StatusText' => 'Busy'],
            'UNAVAILABLE'   => ['Status'=> 4, 'StatusText' => 'Unavailable'],
            'RINGING'       => ['Status'=> 8, 'StatusText' => 'Ringing'],
            'ONHOLD'        => ['Status'=> 16, 'StatusText' => 'On Hold'],
        ];

        if($state === 'INVALID' && $dExists === '1'){
            $result = $stateTable['NOT_INUSE'];
        }else{
            $result = $stateTable[$state]??$stateTable['UNKNOWN'];
        }
        $status = $result['Status'];
        try {
            $json_status = json_encode($result, JSON_THROW_ON_ERROR);
            $this->Verbose("Extension {$number} state is -> $status. JSON:$json_status");
        }catch (\JsonException $e) {
            $this->Verbose("Extension {$number} state is -> $status");
        }
        return $status;
    }

    /**
     * Генерирует языкозависимую версию фразы для IVR меню по идентификатору
     *
     * @param      $phraseId  - id фразы
     * @param null $extension - внутренний номер
     *
     * @return string
     */
    private function getIVRPhrase($phraseId, $extension = null): string
    {
        $resultText = '';
        switch ($phraseId) {
            case 'UnknownNumber':
            {
                $resultText = 'Не правильно набран номер';
                break;
            }
            case 'ExtensionBusy':
            {
                $resultText = 'Абонент сейчас разговаривает, попробуйте позвонить позднее или введите другой номер';
                break;
            }
            case 'ConnectionToExtension':
            {
                $parameters      = [
                    'models'     => [
                        'Extensions' => Extensions::class,
                    ],
                    'columns'    => [
                        'username' => 'Users.username',
                    ],
                    'conditions' => 'Extensions.number = :extension: AND Extensions.is_general_user_number=1',
                    'bind'       => [
                        'extension' => $extension,
                    ],
                    'joins'      => [
                        'Users' => [
                            0 => Users::class,
                            1 => 'Users.id=Extensions.userid',
                            2 => 'Users',
                            3 => 'INNER',
                        ],
                    ],
                    'limit'      => 1,
                ];
                $query      = $this->di->get('modelsManager')->createBuilder($parameters)->getQuery();
                $extensions = $query->execute();
                $extensionRecord = null;
                foreach ($extensions as $record) {
                    $extensionRecord = $record;
                    break;
                }
                $abonentName     = '';
                if ($extensionRecord) {
                    $abonentName = $extensionRecord->username;
                }
                if (empty($abonentName)) {
                    $resultText = 'Соединяю с номером ' . $extension;
                } else {
                    $resultText = 'Соединяю с сотрудником ' . $abonentName;
                }
                break;
            }
            case 'TryAgain':
            {
                $resultText = 'Не вышло связаться с абонентом. Попробуйте еще раз.';
                break;
            }
            default:
                break;
        }

        return $resultText;
    }

    /**
     * Проверяем статус соединения с абонентом
     */
    private function getDialStatus()
    {
        $chan       = $this->agi->get_variable('MASTER_CHANNEL(CHANNEL)', true);
        $am         = Util::getAstManager();
        $DialStatus = $am->GetVar($chan, 'M_DIALSTATUS', null, false);
        if (empty($DialStatus)) {
            $DialStatus = $this->agi->get_variable('DIALSTATUS', true);
        }

        return $DialStatus;
    }

    /**
     * Функция самотестирования
     * При вызове отправляет запрос к 1С на генерацию текста
     * В текст добавляется уникальное число и он отправляется на генерацию
     *
     * Вызывается при каждом редактировании настроек модуля
     *
     * @return PBXApiResult
     * @throws \Exception
     */
    public function selfTest(): PBXApiResult
    {
        $res = new PBXApiResult();
        $res->processor = __METHOD__;
        $this->logger->debug = true;
        $filename            = null;
        $ivrMenuText         = $this->web_service_1C->getIvrMenuText($this->number, 25);

        if (is_array($ivrMenuText)) {
            // При тестировании надо всегда работать с уникальной строкой, чтобы не попадать в кеш
            $ivrMenuText[] = random_int(1, 9999);
            $filename      = $this->text2speech($ivrMenuText);
        } else {
            $messagesFrom1C = $this->web_service_1C->getMessages();
            foreach ($messagesFrom1C as $message) {
                $this->Verbose($message);
            }
        }

        $res->success = $filename !== null;
        $res->messages = $this->messages;

        // Отправим в 1С информацию о внутреннем номере модуля.
        // В FreePBX он фиксированный, в MikoPBX произвольный
        $this->web_service_1C->sendModuleExtension($this->module_extension);

        return $res;
    }

}

