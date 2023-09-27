<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 12 2019
 *
 */

namespace Modules\ModuleSmartIVR\Lib;

use SimpleXMLElement;
use stdClass;

class WebService1C
{
    private $database;
    private $login;
    private $secret;
    private $server_1c_port;
    private $server_1c_host;
    private $library_1c;
    private $use_ssl;
    private $messages;
    private $logger;
    /** @var \Modules\ModuleSmartIVR\Lib\TTSSettings */
    private $tts_settings;


    public function __construct($settings)
    {
        $this->messages = [];

        $this->database       = $settings['database'];
        $this->login          = $settings['login'];
        $this->secret         = $settings['secret'];
        $this->server_1c_host = $settings['server_1c_host'];
        $this->server_1c_port = $settings['server_1c_port'];
        $this->library_1c     = $settings['library_1c'];
        $this->use_ssl        = $settings['use_ssl'];
        $this->logger         = $settings['logger'];
    }

    /**
     * Отправляет на сервер 1С информацию о том какой экстеншен присвоен модулю
     * Актуально для второй версии подсистемы телефонии
     */
    public function sendModuleExtension($number): void
    {
            try {
                if ($this->library_1c === '2.0') {
                    $url = "http://127.0.0.1:8224/setcallbacknumber?number={$number}";
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, []);
                    $result = curl_exec($ch);
                    curl_close($ch);
                } else {
                    $endpoint    = 'MIKO_IVRGenerator4SmartTransfer.1cws';
                    $ivrLink     = "$this->database/ws/$endpoint";
                    $ivrUri      = 'http://wiki.miko.ru/doc:1cajam:smarttransfer';
                    $ivrFunction = 'setcallbacknumber';
                    $result = $this->post1cSoapRequest($number, $ivrLink, $ivrFunction, $ivrUri);
                }
                $this->logger->writeInfo(
                    "1C:Enterprise answered '{$result}' on setcallbacknumber post extension '{$number}'"
                );
            } catch (\Exception $e) {
                $this->logger->writeError(
                    'ConnectionToCRMError: on send SmartIVR extension to 1C:Enterprise' . PHP_EOL . $e->getMessage()
                );
            }

    }

    /**
     * Получение данных из 1С по SOAP для панели телефонии 1
     *
     * @param      $wslink
     * @param      $wsfunction
     * @param      $wsuri
     * @param bool $relogin
     *
     * @return null| \SimpleXMLElement
     */
    private function post1cSoapRequest($number, $wslink, $wsfunction, $wsuri, $relogin = false): ?SimpleXMLElement
    {
        $result = null;

        $xmlDocumentTpl = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
            '<soap:Body>' .
            '<m:%function% xmlns:m="%uri%">' .
            '<m:Number>%number%</m:Number>' .
            '</m:%function%>' .
            '</soap:Body>' .
            '</soap:Envelope>';

        $xmlDocument = str_replace(
            [
                '%function%',
                '%uri%',
                '%number%',
            ],
            [
                $wsfunction,
                $wsuri,
                $number,
            ],
            $xmlDocumentTpl
        );


        $curl = curl_init();
        if ($this->use_ssl) {
            $url = "https://{$this->server_1c_host}:{$this->server_1c_port}/{$wslink}";
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        } else {
            $url = "http://{$this->server_1c_host}:{$this->server_1c_port}/{$wslink}";
        }

        $ckfile = '/tmp/module_smart_ivr_1c_session_cookie.txt';

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xmlDocument);
        curl_setopt($curl, CURLOPT_USERPWD, "{$this->login}:{$this->secret}");
        curl_setopt($curl, CURLOPT_TIMEOUT, 6);

        if ($relogin) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['IBSession: start']);
            curl_setopt($curl, CURLOPT_COOKIEJAR, $ckfile);
        } else {
            curl_setopt($curl, CURLOPT_COOKIEFILE, $ckfile);
        }
        $resultRequest = curl_exec($curl);
        $http_code     = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $have_error = false;
        if (0 === $http_code) {
            $errorDescription = 'ConnectionToCRMError: No access to 1C:Enterprise.' . PHP_EOL .
                "We use the next params: server:{$this->server_1c_host}," .
                "port:{$this->server_1c_port}, login: {$this->login}, " .
                "password: {$this->secret}, method: ${wsfunction}," . PHP_EOL .
                " url: $url" . PHP_EOL . 'We try POST xml: ' . $xmlDocument;
            $this->messages[] = $errorDescription;
            $this->logger->writeError($errorDescription);
            $have_error = true;
        } elseif ( ! $relogin && in_array($http_code, [400, 500], false)) {
            return $this->post1cSoapRequest($number, $wslink, $wsfunction, $wsuri, true);
        } elseif (in_array($http_code, [401, 403], false)) {
            $errorDescription = "ConnectionToCRMError: HTTP code $http_code check username or password 1C:Enterprise.
             Method: ${wsfunction}.";
            $this->messages[] = $errorDescription;
            $this->logger->writeError($errorDescription);
            $have_error = true;
        } elseif ($http_code !== 200) {
            $errorDescription = "ConnectionToCRMError: HTTP code $http_code 1C:Enterprise. Method: $wsfunction. HTTP_CODE: $http_code";
            $this->logger->writeError($errorDescription);
            $this->messages[] = $errorDescription;
            $have_error       = true;
        }

        if ( ! $have_error) {
            // Парсим SOAP ответ
            libxml_use_internal_errors(true);

            $xml = simplexml_load_string($resultRequest, null, null, 'http://schemas.xmlsoap.org/soap/envelope/');
            if ($xml !== false) {
                $ns                 = $xml->getNamespaces(true);
                $soap               = $xml->children($ns['soap']);
                $getAddressResponse = $soap->Body->children($ns['m']);
                $functionResultName = $wsfunction . 'Response';
                $result             = $getAddressResponse->$functionResultName->children($ns['m'])->return;
            }
        }

        return $result;
    }

    /**
     * Получаем из 1С IVR меню
     *
     * @param $number string номер по которому делаем запрос
     * @param $timeout int сколько секунд ждем ответ от сервиса
     *
     * @return array|null
     */
    public function getIvrMenuText(string $number, int $timeout=5): ?array
    {
        $arr_textToSpeech = null;
        try {
            if ($this->library_1c === '2.0') {
                $url = "http://127.0.0.1:8224/getivrtext?number={$number}";
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $input_json = curl_exec($ch);
                $input_obj  = json_decode($input_json, false);
                curl_close($ch);
                if ($input_json !== null
                    && json_last_error() === JSON_ERROR_NONE
                    && property_exists($input_obj,'result')
                    && $input_obj->result==='Success'){
                    $this->tts_settings       = $input_obj->data;
                    $this->tts_settings->auth = $this->fillTTSAuthSettings();
                    $arr_textToSpeech         = $input_obj->data->texttospeech;
                } elseif($input_json !== null
                    && json_last_error() === JSON_ERROR_NONE
                    && property_exists($input_obj,'result')
                    && property_exists($input_obj,'cause')
                    && $input_obj->result==='Error') {
                    $errorDescription = 'CRM returns error: '.$input_obj->cause . PHP_EOL .
                        'Call will be redirected to failover extension';
                    $this->messages[] = $errorDescription;
                    $this->logger->writeError($errorDescription);
                } else {
                    $errorDescription = 'ConnectionToCRMError: Error parse data from 1C:Enterprise.' . PHP_EOL .
                        'Call will be redirected to failover extension';
                    $this->messages[] = $errorDescription;
                    $this->logger->writeError($errorDescription);
                }
            } else {
                $endpoint = 'MIKO_IVRGenerator4SmartTransfer.1cws';
                $ivrLink     = "$this->database/ws/$endpoint";
                $ivrUri      = 'http://wiki.miko.ru/doc:1cajam:smarttransfer';
                $ivrFunction = 'getivrtext';
                $input_json = $this->post1cSoapRequest($number, $ivrLink, $ivrFunction, $ivrUri);
                $input_obj  = json_decode($input_json, false);
                if ($input_json !== null && json_last_error() === JSON_ERROR_NONE) {
                    $this->tts_settings       = $input_obj;
                    $this->tts_settings->auth = $this->fillTTSAuthSettings();
                    $arr_textToSpeech         = $input_obj->texttospeech;
                }
            }
        } catch (\Exception $e) {
            $errorDescription = 'ConnectionToCRMError: Error on get IVRMenu request to 1C:Enterprise'
                . PHP_EOL . $e->getMessage();
            $this->messages[] = $errorDescription;
            $this->logger->writeError($errorDescription);
        }

        $this->logger->writeInfo($arr_textToSpeech);
        return $arr_textToSpeech;
    }

    /**
     * Подставляет настройки авторизации если они не пришли в ответи из 1С
     */
    private function fillTTSAuthSettings()
    {
        $tts_service = $this->tts_settings->tts_service;
        $result      = [];
        switch ($tts_service) {
            case 'Yandex':
            {
                if (property_exists($this->tts_settings, 'api_key')
                    && isset($this->tts_settings->api_key)) {
                    $result = ['api_key' => $this->tts_settings->api_key];
                }
                break;
            }
            case 'CRT':
            {
                if (property_exists($this->tts_settings, 'auth')
                    && isset($this->tts_settings->auth)) {
                    $result = $this->tts_settings->auth;
                }
                break;
            }
            default:
                $errorDescription = "Unknown TTS service: $tts_service";
                $this->messages[] = $errorDescription;
                $this->logger->writeError($errorDescription);
        }

        return $result;
    }

    /**
     * Return error or verbose messages
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Return TTSSettings object
     *
     * @return \stdClass
     */
    public function getTTSSettings(): ?stdClass
    {
        return $this->tts_settings;
    }
}