<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

return [
    'mo_ModuleSmartIVR'        => 'Модуль умной маршрутизации',
    'repModuleSmartIVR'        => 'Модуль умной маршрутизации - %represent%',
    'BreadcrumbModuleSmartIVR' => 'Модуль умной маршрутизации вызовов (Smart IVR)',
    'SubHeaderModuleSmartIVR'  => 'Генерация голосового меню на лету по данным CRM системы',

    'module_smivr_LibraryVer1'   => 'Версия 1.0',
    'module_smivr_LibraryVer2'   => 'Версия 2.0/4.0',
    'module_smivr_LibraryVer2NotInstalled' => 'Установите модуль панель телефонии 4.0 для 1С, включите и настройте его',
    'module_smivr_Library1CType' => 'Версия подсистемы интеграции на стороне 1С',

    'module_smivr_Server1CHostPort'  => 'Адрес и порт сервера 1С',
    'module_smivr_UseSSLConnection'  => 'Использовать SSL',
    'module_smivr_Login'             => 'Логин для веб-сервиса 1C',
    'module_smivr_Password'          => 'Пароль для авторизации в 1С',
    'module_smivr_PublicationName'   => 'Имя публикации',
    'module_smivr_NumberOfRepeat'    => 'Количество повторов IVR меню перед переводом звонка номер по-умолчанию',
    'module_smivr_TimeoutExtension'  => 'Номер по-умолчанию',
    'module_smivr_FailoverExtension' => 'Номер, куда отправим звонок в случае сбоев связи с 1С и TTS. Например, статическое IVR меню.',
    'module_smivr_EnableDebugMode'   => 'Включить режим отладки модуля',

    'module_smivr_ValidateServer1CHostEmpty' => 'Не заполнен адрес сервера 1С',
    'module_smivr_ValidateServer1CPortRange' => 'Не правильно указан порт сервера 1С',
    'module_smivr_ValidatePubName'           => 'Не указано имя публикации веб-сервиса 1С',
    'module_smivr_ValidateNumberOfRepeat'    => 'Не верно указано количество повторов IVR меню',
    'module_smivr_ValidateTimeoutExtension'  => 'Не указан номер по-умолчанию',
    'module_smivr_ValidateFailOverExtension' => 'Не указан аварийный номер',
    'module_smivr_ValidateFailOverExtensionNotEqualTo'=>'Неверно указан аварийный номер',
    'module_smivr_ValidateTimeOutExtensionNotEqualTo'=>'Неверно указан номер по-умолчанию',


    'module_smivr_Connected'       => 'Подключен к 1С и TTS службе',
    'module_smivr_Disconnected'    => 'Отключен',
    'module_smivr_Disconnected1C'  => 'Ошибка соединения с 1С',
    'module_smivr_DisconnectedTTS' => 'Ошибка соединения с TTS сервисом',
    'module_smivr_UpdateStatus'    => 'Обновление статуса',

    'module_smivr_UpdateRecord' => 'При изменении статуса модуля обновлены объекты:<br>',

    'module_smivr_ErrorOnMakeTestIVR' => 'Ошибка при тестовой генерации голосового меню',
    'module_smivr_WeGetSettingsFromCTIClient'=>'Будет использоваться канал связи из модуля "Панель телефонии 4.0 для 1С"',

    'module_smivr_TimeoutExtForeignKey'=>'Используется как номер по-умолчанию в модуле умной маршрутизации',
    'module_smivr_FailoverExtForeign'=>'Используется как аварийный номер в модуле умной маршрутизации',
    'module_smivr_lastResponsibleTime'=>'Количество минут, за которое следует анализировать CDR для поиска ответственного',
    'module_smivr_lastResponsibleDuration'=>'Количество секунд, как долго звонить последнему ответственному',
];