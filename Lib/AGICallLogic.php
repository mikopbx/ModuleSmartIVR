<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 9 2020
 */

namespace Modules\ModuleSmartIVR\Lib;

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

            $this->logger->debug  = $settings->debug_mode === '1';
            $this->web_service_1C = new WebService1C($params1C);
        }
    }

    /**
     * Начало работы IVR.
     */
    public function startIVR(): void
    {
        $this->agi    = new AGI();
        $this->number = $this->agi->request['agi_callerid'];
        $this->logger->writeInfo("Preparing IVR for {$this->number}");
        $DialStatus = '';
        $this->agi->exec('Ringing', '');
        $this->agi->set_variable('AGIEXITONHANGUP', 'yes');
        $this->agi->set_variable('AGISIGHUP', 'yes');
        $this->agi->set_variable('__ENDCALLONANSWER', 'yes');

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
            $this->Verbose('Call answered the script sends HANGUP command to PBX');
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
        /**
         * -1 = Extension not found
         * 0 = Idle
         * 1 = In Use
         * 2 = Busy
         * 4 = Unavailable
         * 8 = Ringing
         * 16 = On Hold
         */
        $ami = Util::getAstManager('off');
        if ( ! $ami->loggedIn()) {
            return -1;
        }
        $res = $ami->ExtensionState($number, 'internal-hints');

        if (!array_key_exists('Status', $res)){
            return -1;
        }

        $status = (int)$res['Status'];
        if ($status === -1) {
            // Проверим, есть ли "приложение" в системе.
            $res    = Extensions::findFirst("number='{$number}'");
            $status = ($res === null) ? -1 : 0;
        }

        $json_status = json_encode($res);
        $this->Verbose("Extension {$number} state is -> $status. JSON:$json_status");

        $ami->disconnect();

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
