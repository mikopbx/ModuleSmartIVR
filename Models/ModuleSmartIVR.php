<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

namespace Modules\ModuleSmartIVR\Models;

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Modules\Models\ModulesModelsBase;
use Phalcon\Mvc\Model\Relation;

class ModuleSmartIVR extends ModulesModelsBase
{
    /**
     * @Primary
     * @Identity
     * @Column(type="integer", nullable=false)
     */
    public $id;

    /**
     * Адрес сервера 1С
     *
     * @Column(type="string", nullable=true)
     */
    public $server1chost;

    /**
     * Порт, где опубликован сервер 1С
     *
     * @Column(type="integer", default="80", nullable=true)
     */
    public $server1cport;

    /**
     * Логин к вебсервису
     *
     * @Column(type="string", nullable=true)
     */
    public $login;

    /**
     * Пароль к вебсервису
     *
     * @Column(type="string", nullable=true)
     */
    public $secret;

    /**
     *  Extension, на которой пойдет звонок по таймауту
     *
     * @Column(type="string", nullable=true)
     */
    public $timeout_extension;

    /**
     * Резервный Extension, на которой пойдет звонок в случае сбоя
     *
     * @Column(type="string", nullable=true)
     */
    public $failover_extension;

    /**
     * Имя публикации
     *
     * @Column(type="string", nullable=true)
     */
    public $database;

    /**
     * Enable HTTPS
     *
     * @Column(type="integer", default="0", nullable=true)
     */
    public $useSSL;

    /**
     * Занятый приложением внутренний номер доступный в списках выбора
     *
     * @Column(type="string", nullable=true)
     */
    public $extension;

    /**
     * JSON с объектами, в которых были ссылки на этот модуль
     *
     * @Column(type="string", nullable=true)
     */
    public $redirection_settings;

    /**
     * Максимальное число повторов меню перед отправкой на номер по умолчанию
     *
     * @Column(type="integer", default="1", nullable=true)
     */
    public $number_of_repeat;

    /**
     * Версия подсистемы телефонии
     *
     * @var string {1.0, 2.0}
     * @Column(type="string", default="1.0", nullable=true)
     */
    public $library_1c;

    /**
     * Режим отладки модуля
     *
     * @Column(type="integer", default="120", nullable=true)
     */
    public $debug_mode;

    /**
     * Количество минут, за которое необходимо анализировать CDR
     * @Column(type="integer", default="30", nullable=true)
     */
    public $last_responsible_time;

    /**
     * Количество секунд. Как должго звонить последнему ответственному.
     * @Column(type="integer", default="0", nullable=true)
     */
    public $last_responsible_duration;

    /**
     * Returns dynamic relations between module models and common models
     * MikoPBX check it in ModelsBase after every call to keep data consistent
     *
     * There is example to describe the relation between Providers and ModuleTemplate models
     *
     * It is important to duplicate the relation alias on message field after Models\ word
     *
     * @param $calledModelObject
     *
     * @return void
     */
    public static function getDynamicRelations(&$calledModelObject): void
    {
        if (is_a($calledModelObject, Extensions::class)) {
            $calledModelObject->hasMany(
                'number',
                __CLASS__,
                'failover_extension',
                [
                    'alias'      => 'ModuleSmartIVRFailOver',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => $calledModelObject->t('module_smivr_FailoverExtForeign'),
                        'action'     => Relation::ACTION_RESTRICT,
                    ],
                ]
            );
            $calledModelObject->hasMany(
                'number',
                __CLASS__,
                'timeout_extension',
                [
                    'alias'      => 'ModuleSmartIVRTimeout',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => $calledModelObject->t('module_smivr_TimeoutExtForeignKey'),
                        'action'     => Relation::ACTION_RESTRICT,
                    ],
                ]
            );
            $calledModelObject->belongsTo(
                'number',
                __CLASS__,
                'extension',
                [
                    'alias'      => 'ModuleSmartIVR',
                    'foreignKey' => [
                        'allowNulls' => 0,
                        'message'    => 'ModuleSmartIVR',
                        'action'     => Relation::NO_ACTION,
                    ],
                ]
            );
        }
    }

    public function initialize(): void
    {
        $this->setSource('m_ModuleSmartIVR');
        parent::initialize();
        $this->hasOne(
            'failover_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsFailOver',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::NO_ACTION,
                ],
            ]
        );
        $this->hasOne(
            'timeout_extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'ExtensionsTimeout',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::NO_ACTION,
                ],
            ]
        );
        $this->hasOne(
            'extension',
            Extensions::class,
            'number',
            [
                'alias'      => 'Extensions',
                'foreignKey' => [
                    'allowNulls' => false,
                    'action'     => Relation::ACTION_CASCADE,
                ],
            ]
        );
    }


}