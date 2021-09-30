<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2019
 *
 */

namespace Modules\ModuleSmartIVR\Lib;


class SpeechProTTS implements TTSInterface
{

    private $voices;
    private $session_id;
    private $ttsDir;
    private $credentials;
    private $countAuthFailures;
    private $messages;
    private $logger;

    /**
     * SpeechProTTS constructor.
     *
     * @param $settings array of settings [ttsDir, auth, logger]
     */
    public function __construct($settings)
    {
        $this->ttsDir            = $settings['ttsDir'];
        $this->logger            = $settings['logger'];
        $this->credentials       = $settings['auth'];
        $this->countAuthFailures = 0;
        $this->messages          = [];

        // Получим ID предыдущей сессии
        if (file_exists("$this->ttsDir/speechpro.session")) {
            $this->session_id = file_get_contents("$this->ttsDir/speechpro.session");
        } else {
            $this->startSessionKey();
        }
        $this->voices = $this->getAvailableVoices();
    }

    /**
     * Start speechPro session
     *
     */
    private function startSessionKey(): void
    {
        $curl = curl_init();

        $headers = [
            'Content-Type: application/json',
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->credentials));
        curl_setopt($curl, CURLOPT_URL, 'https://cp.speechpro.com/vksession/rest/session');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (200 === $http_code) {
            $data             = json_decode($response, true);
            $this->session_id = $data['session_id'];
            file_put_contents("$this->ttsDir/speechpro.session", $this->session_id);
            $this->countAuthFailures = 0;
            $this->logger->writeInfo("We get new session key for speechpro $this->session_id");
        } else {
            if (file_exists("$this->ttsDir/speechpro.session")) {
                @unlink("$this->ttsDir/speechpro.session");
            }
            $this->logger->writeError(
                'TTSConnectionError: when we are trying to authorize on https://cp.speechpro.com we got http-code: ' . $http_code
            );
        }
    }

    /**
     * Get available voices fro speech pro
     *
     * @return array
     */
    private function getAvailableVoices(): array
    {
        $availableVoices = [];

        $curl    = curl_init();
        $headers = [
            'Content-Type: application/json',
            "X-Session-Id: $this->session_id",
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_URL, 'https://cp.speechpro.com/vktts/rest/v1/languages/Russian/voices');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (200 === $http_code) {
            $data = json_decode($response, true);
            if (is_array($data)) {
                foreach ($data as $voice) {
                    $availableVoices[] = $voice['name'];
                }
            }
            $this->logger->writeInfo(
                'Speechpro returns available voices: ' . implode(
                    ' ',
                    $availableVoices
                )
            );
        } elseif (401 === $http_code && $this->countAuthFailures < 2) {
            $this->startSessionKey();

            return $this->getAvailableVoices();
        } else {
            $errorDescription = 'TTSConnectionError: when we tried to get voices from 
            https://cp.speechpro.com we got http-code: ' . $http_code;
            $this->logger->writeError($errorDescription);
            $this->messages[] = $errorDescription;
        }

        return $availableVoices;
    }

    /**
     * Генерация звукового файла
     *
     * @param $text  - текст для генерации
     * @param $voice - Голос
     *
     * @return string|null - путь к файлу генерации без расширения
     */
    public function Synthesize($text, $voice): ?string
    {
        $this->logger->writeInfo('Start synthesis by Speechpro:' . PHP_EOL . implode($text) . PHP_EOL . $voice);

        if ( ! in_array($voice, $this->voices, true)) {
            $this->logger->writeInfo("Voice $voice doesn't exist. We will use default Julia_8000n.");
            $voice = 'Julia_8000n';
        }
        if (is_array($text) && count($text) > 1) {
            $result = $this->makeSpeechFromTextArray($text, $voice);
        } elseif (is_array($text)) {
            $text   = implode($text);
            $result = $this->makeSpeechFromText($text, $voice);
        } else {
            $result = $this->makeSpeechFromText($text, $voice);
        }
        if ( ! empty($result)) {
            $this->logger->writeInfo("Synthesis result file: $result");
        } else {
            $this->logger->writeError('Synthesis failure');
            $this->messages[] = 'Synthesis by Speechpro failure';
        }

        return $result;
    }

    /**
     * Генерирует единый файл записи из массива текстовых фраз.
     *
     * @param $arr_text_to_speech - array of phrases
     * @param $voice
     *
     * @return null|string
     */
    private function makeSpeechFromTextArray($arr_text_to_speech, $voice): ?string
    {
        $this->messages[] = 'We got array of sentences';
        $trim = (urldecode($voice) === 'Lidiya8000' || urldecode($voice) === 'Лидия8000') ? '0' : '0.3';
        //Этих голосов нет, может уже и нет смысла в тримировании
        $trim          = '0';
        $ivr_extension    = '.wav';
        $ivr_filename     = md5(implode($arr_text_to_speech) . $voice);
        $exception        = false;

        // Проверим нет ли ранее сгенерированного полного файла записи.
        $fullFileName = $this->ttsDir . $ivr_filename . $ivr_extension;
        $this->logger->writeInfo(
            'We got array of sentences. Searching cache for result file (for voice ' . $voice . '): ' .
            PHP_EOL . $fullFileName . ' Trimming silence is ' . $trim . ' sec'
        );
        if (file_exists($fullFileName) && filesize($fullFileName) > 0) {
            $this->logger->writeInfo(
                'TTS found cached result file (for voice ' . $voice . '): ' . PHP_EOL . urldecode(
                    implode(
                        ' ',
                        $arr_text_to_speech
                    )
                ) . ' - ' . PHP_EOL . $fullFileName
            );

            return $this->ttsDir . $ivr_filename;
        }

        $i         = 0; // Порядковый номер фразы в массиве.
        $extension = 'raw';
        $soxarg    = '-r 8000 -e signed-integer -b 16 -c 1 -t'; // Настройки для работы с raw файлами

        $resultExecArg = ''; // Строка с параметрами SOX для склеивания файла
        $filesList     = [];
        // Обойдем все фразы в массиве текстов
        foreach ($arr_text_to_speech as $text_to_speech) {
            $record_file = $this->makeSpeechFromText($text_to_speech, $voice);
            if ($record_file === null) { // Ошибка генерации. используем запись по умолчанию
                $exception = true;
                break;
            }
            if (file_exists("{$record_file}.wav")) {
                // Для нового варианта API будет этот формат.
                $extension = 'wav';
            }

            if ($trim !== '0') {
                $command = "sox {$soxarg} {$extension} {$record_file}.{$extension}  {$this->ttsDir}_tmp_" . basename(
                        $record_file
                    ) . ".{$extension}";
                // Нужно тримировать записи
                if ($i > 0) {
                    $command .= " trim {$trim}";
                }
                exec("$command 2>&1");
                $resultExecArg .= " {$soxarg} {$extension} {$this->ttsDir}_tmp_" . basename($record_file) . ".{$extension}";
                $filesList[]   = "{$this->ttsDir}_tmp_" . basename($record_file) . ".{$extension}";
                $filesList[]   = "{$record_file}.wav";
            } else {
                $resultExecArg .= " $soxarg {$extension} {$record_file}.{$extension}";
            }
            $i++;
        }

        $resultCmd   = [];
        $result_file = null;
        if ( ! $exception) {
            //сшиваем полученные файлы в один
            //имя результирующего файла для всего массива с учетом нужного голоса
            $fullFileName = $this->ttsDir . $ivr_filename . $ivr_extension;
            $this->logger->writeInfo('Try to merge files by command: ' . 'sox ' . $resultExecArg . ' ' . $fullFileName);
            exec('sox ' . $resultExecArg . ' ' . $fullFileName . ' 2>&1', $resultCmd);
            if (file_exists($fullFileName) && filesize($fullFileName) > 0) {
                $result_file = $this->ttsDir . $ivr_filename;
                $this->logger->writeInfo('Result file successfully created : ' . $result_file);
            } else {
                $this->logger->writeError('Error creating result file : ' . implode($resultCmd));
                $resultCmd = [];
            }
        }
        // Удаляем исходные файлы в папках
        if (count($filesList) > 0) {
            $command = implode(' ', $filesList);
            exec("rm -rf $command 2>&1");
        }

        return $result_file;
    }

    /**
     * Генерирует и скачивает в на внешний диск файл с речью.
     *
     *
     * @param $text_to_speech - генерируемый текст
     * @param $voice          - голос
     *
     * @return null|string
     */
    private function makeSpeechFromText($text_to_speech, $voice): ?string
    {
        $speech_extension        = '_src.wav';
        $result_extension        = '.wav';
        $speech_filename         = md5($text_to_speech . $voice);
        $fullFileName            = $this->ttsDir . $speech_filename . $result_extension;
        $fullFileNameFromService = $this->ttsDir . $speech_filename . $speech_extension;
        // Проверим вдург мы ранее уже генерировали такой файл.
        if (file_exists($fullFileName) && filesize($fullFileName) > 0) {
            $this->logger->writeInfo(
                'TTS found in the cache early created file (voice ' . urldecode($voice) . '): ' . PHP_EOL . urldecode(
                    $text_to_speech
                ) . ' - ' . PHP_EOL . $fullFileName
            );

            return $this->ttsDir . $speech_filename;
        }

        $headers = [
            'Content-Type: application/json',
            "X-Session-Id: $this->session_id",
        ];

        $post_vars = [
            'voice_name' => $voice,
            'text'       => [
                'mime'  => 'text/plain',
                'value' => urldecode($text_to_speech),
            ],
            'audio'      => 'audio/wav',
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_POST, count($post_vars));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post_vars));
        curl_setopt($curl, CURLOPT_URL, 'https://cp.speechpro.com/vktts/rest/v1/synthesize');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (200 === $http_code && json_decode($response, false) !== null) {
            $response_data = json_decode($response, true);
            if (isset($response_data['data'])) {
                file_put_contents($fullFileNameFromService, base64_decode($response_data['data']));
            }
            exec("/usr/bin/sox -v 0.99 -G '{$fullFileNameFromService}' -c 1 -r 8000 -b 16 '{$fullFileName}' 2>&1");
            if (file_exists($fullFileName)) {
                // Файл успешно сгененрирован
                $this->logger->writeInfo(
                    'Successful generation : ' . PHP_EOL . $fullFileNameFromService . ' with original file size ' . filesize(
                        $fullFileNameFromService
                    )
                );
                // Удалим ненужный более raw файл.
                @unlink($fullFileNameFromService);

                return $this->ttsDir . $speech_filename;
            }
        } elseif (401 === $http_code && $this->countAuthFailures <= 2) {
            $this->logger->writeInfo('Speechpro returns connection error 401. Restarting session...');
            $this->startSessionKey();

            return $this->makeSpeechFromText($text_to_speech, $voice);
        } elseif (401 === $http_code && $this->countAuthFailures > 2) {
            $errorDescription = 'TTSConnectionError: when we are trying to get sound from 
            https://cp.speechpro.com we got http-code: 401 ' . PHP_EOL . 'We use ' . implode(
                    ' ;',
                    $this->credentials
                ) . ' header for auth';
            $this->messages[] = $errorDescription;
            $this->logger->writeError($errorDescription);
        } else {
            $errorDescription = 'TTSConnectionError: when we are trying to get sound from 
            https://cp.speechpro.com we got http-code: ' . $http_code . PHP_EOL . 'We use the next params:' . PHP_EOL . json_encode(
                    $post_vars
                );
            if ( ! empty($response)) {
                $errorDescription .= PHP_EOL . 'Response:' . $response;
            }
            $this->messages[] = $errorDescription;
            $this->logger->writeError($errorDescription);
            // Удалим raw файл, если какой-либо создавался.
            if (file_exists($fullFileNameFromService)) {
                @unlink($fullFileNameFromService);
            }
        }

        return null;
    }

    /**
     * Return error or verbose messages
     *
     * @return mixed
     */
    public function getMessages(): array
    {
        return $this->messages;
    }


}