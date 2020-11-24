<?php
/**
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 12 2019
 *
 */

namespace Modules\ModuleSmartIVR\Lib;


interface TTSInterface
{
    /**
     * TTS constructor
     *
     * @param $settings array of settings [ttsDir, auth, logger]
     */
    public function __construct($settings);


    /**
     * Генерация звукового файла
     *
     * @param $text  - текст для генерации
     * @param $voice - голос
     *
     * @return string|null - путь к файлу генерации без расширения
     */
    public function Synthesize($text, $voice): ?string;

    /**
     * Return error or verbose messages
     *
     * @return mixed
     */
    public function getMessages(): array;
}