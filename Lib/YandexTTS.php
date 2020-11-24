<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2019
 *
 */

namespace Modules\ModuleSmartIVR\Lib;

class YandexTTS implements TTSInterface
{

    private $voices;
    private $api_key;
    private $ttsDir;
    private $messages;
    private $logger;

    /**
     * YandexTTS constructor.
     *
     * @param $settings array of settings [ttsDir, auth, logger]
     */
    public function __construct($settings)
    {
        $this->voices = [
            'oksana',
            'jane',
            'omazh',
            'zahar',
            'ermil',
            'alena',
            'filipp',
        ];

        $this->messages = [];

        $auth         = $settings['auth'];
        $this->ttsDir = $settings['ttsDir'];
        $this->logger = $settings['logger'];

        if (is_array($auth)) {
            $this->api_key = $auth['api_key'];
        } else {
            $this->api_key = $auth->api_key;
        }
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
        if ( ! in_array($voice, $this->voices, true)) {
            $this->logger->writeInfo("Voice $voice doesn't exist. We will use default jane.");
            $voice = 'jane';
        }

        $this->logger->writeInfo('Start synthesis by YandexTTS');
        $this->logger->writeInfo($text);

        if (is_array($text) && count($text) > 1) {
            $result = $this->makeSpeechFromTextArray($text, $voice);
        } elseif (is_array($text)) {
            $text   = implode($text);
            $text   = urldecode($text);
            $result = $this->makeSpeechFromText($text, $voice);
        } else {
            $result = $this->makeSpeechFromText($text, $voice);
        }
        if ( ! empty($result)) {
            $this->logger->writeInfo("Synthesis result file: $result");
        } else {
            $this->logger->writeError('Synthesis failure');
            $this->messages[] = 'Synthesis by YandexTTS failure';
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
        $trim             = 0;
        $ivr_extension    = '.wav';
        $ivr_filename     = md5(implode($arr_text_to_speech) . $voice);
        $exception        = false;

        // Проверим нет ли ранее сгенерированного полного файла записи.
        $fullFileName = $this->ttsDir . $ivr_filename . $ivr_extension;
        if (file_exists($fullFileName) && filesize($fullFileName) > 0) {
            $this->logger->writeInfo(
                'TTS found in the cache for: ' . urldecode(
                    implode(
                        ' ',
                        $arr_text_to_speech
                    )
                ) . ' file: ' . $fullFileName
            );

            return $this->ttsDir . $ivr_filename;
        }

        $i         = 0; // Порядковый номер фразы в массиве.
        $extension = 'raw';
        $soxarg    = '-r 8000 -e signed-integer -b 16 -c 1 -t'; // Настройки для работы с raw файлами

        $resultExecArg = ''; // Строка с параметрами SOX для склеивания файла
        $filesList     = [];
        foreach ($arr_text_to_speech as $text_to_speech) {  // Обойдем все фразы в массиве текстов
            $record_file = $this->makeSpeechFromText($text_to_speech, $voice);
            if ($record_file === null) { // Ошибка генерации. используем запись по умолчанию
                $exception = true;
                break;
            }
            if (file_exists("{$record_file}.wav")) {
                // Для нового варианта API будет этот формат.
                $extension = 'wav';
            }

            if ($trim > 0) {
                $command = "sox {$soxarg} {$extension} {$record_file}.{$extension}  /tmp/" . basename(
                        $record_file
                    ) . ".{$extension}";
                // Нужно тримировать записи
                if ($i > 0) {
                    $command .= " trim {$trim}";
                }
                exec("$command 2>&1");
                $resultExecArg .= " {$soxarg} {$extension} /tmp/" . basename($record_file) . ".{$extension}";
                $filesList[]   = '/tmp/' . basename($record_file) . ".{$extension}";
                $filesList[]   = "{$record_file}.wav";
            } else {
                $resultExecArg .= " $soxarg {$extension} {$record_file}.{$extension}";
            }
            $i++;
        }

        $result_file = null;
        if ( ! $exception) {
            $fullFileName = $this->ttsDir . $ivr_filename . $ivr_extension;
            exec('sox ' . $resultExecArg . ' ' . $fullFileName . ' 2>&1');
            if (file_exists($fullFileName) && filesize($fullFileName) > 0) {
                $result_file = $this->ttsDir . $ivr_filename;
            }
        }
        if (count($filesList) > 0) {
            $command = implode(' ', $filesList);
            exec("rm -rf $command 2>&1");
        }

        return $result_file;
    }

    /**
     * Генерирует и скачивает в на внешний диск файл с речью.
     *
     * @param $text_to_speech - генерируемый текст
     * @param $voice          - голос
     *
     * @return null|string
     *
     * https://tts.api.cloud.yandex.net/speech/v1/tts:synthesize
     */

    private function makeSpeechFromText($text_to_speech, $voice): ?string
    {
        $speech_extension        = '.raw';
        $result_extension        = '.wav';
        $speech_filename         = md5($text_to_speech . $voice);
        $fullFileName            = $this->ttsDir . $speech_filename . $result_extension;
        $fullFileNameFromService = $this->ttsDir . $speech_filename . $speech_extension;

        // Проверим вдург мы ранее уже генерировали такой файл.
        if (file_exists($fullFileName) && filesize($fullFileName) > 0) {
            $this->logger->writeInfo(
                'TTS found in the cache for: ' . urldecode($text_to_speech . $voice) . ' file: ' . $fullFileName
            );

            return $this->ttsDir . $speech_filename;
        }

        // Файла нет в кеше, будем генерировать новый.
        $post_vars = [
            'lang'            => 'ru-RU',
            'format'          => 'lpcm',
            'speed'           => '1.0',
            'sampleRateHertz' => '8000', // {8000, 16000, 48000}
            'voice'           => $voice,
            'text'            => urldecode($text_to_speech),
        ];

        $fp   = fopen($fullFileNameFromService, 'w');
        $curl = curl_init();

        $headers = [
            "Authorization: Api-Key $this->api_key",
        ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_vars));
        curl_setopt($curl, CURLOPT_URL, 'https://tts.api.cloud.yandex.net/speech/v1/tts:synthesize');
        curl_exec($curl);
        $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        fclose($fp);

        if (200 === $http_code && file_exists($fullFileNameFromService) && filesize($fullFileNameFromService) > 0) {
            $this->logger->writeInfo(
                'Successful generation into: ' . $fullFileNameFromService
                . ' file size: ' . filesize($fullFileNameFromService)
            );
            exec("sox -r 8000 -e signed-integer -b 16 -c 1 -t raw {$fullFileNameFromService} {$fullFileName}");

            if (file_exists($fullFileName)) {
                // Удалим raw файл.
                @unlink($fullFileNameFromService);

                // Файл успешно сгененрирован
                return $this->ttsDir . $speech_filename;
            }
        } else {
            if (file_exists($fullFileNameFromService)) {
                @unlink($fullFileNameFromService);
            }
        }
        $errorDescription = "TTSConnectionError: when we are trying to get sound from
             https://tts.api.cloud.yandex.net we got http-code: $http_code" . PHP_EOL . "We use Api-Key $this->api_key header for auth";
        $this->messages[] = $errorDescription;
        $this->logger->writeError($errorDescription);

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