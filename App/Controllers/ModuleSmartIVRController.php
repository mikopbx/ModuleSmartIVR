<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
 */

namespace Modules\ModuleSmartIVR\App\Controllers;

use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\PbxExtensionModules;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleSmartIVR\Models\ModuleSmartIVR;
use Modules\ModuleSmartIVR\App\Forms\ModuleSmartIVRForm;

class ModuleSmartIVRController extends BaseController
{

    private string $moduleUniqueID = 'ModuleSmartIVR';
    private string $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir           = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.png";
        $this->view->submitMode    = null;
        parent::initialize();
    }

    /**
     * Форма настроек модуля
     */
    public function indexAction(): void
    {
        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs('js/pbx/Extensions/extensions.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-smartivr-index.js", true);

        $settings = ModuleSmartIVR::findFirst();
        if ($settings === null) {
            $settings = new ModuleSmartIVR();
        }

        // Список всех используемых эктеншенов
        $forwardingExtensions[''] = $this->translation->_('ex_SelectNumber');
        $parameters               = [
            'conditions' => 'number IN ({ids:array})',
            'bind'       => [
                'ids' => [
                    $settings->failover_extension,
                    $settings->timeout_extension,
                ],
            ],
        ];
        $extensions               = Extensions::find($parameters);
        foreach ($extensions as $record) {
            $forwardingExtensions[$record->number] = $record ? $record->getRepresent() : '';
        }
        $this->view->extension = $settings->extension;

        $parameters = [
            'conditions'=>'uniqid="ModuleCTIClient" and disabled!="1"'
        ];
        $this->view->moduleCTI2Installed = PbxExtensionModules::count($parameters)>0;

        $options = [
            'extensions' => $forwardingExtensions
        ];

        $this->view->form = new ModuleSmartIVRForm($settings, $options);
        $this->view->pick("{$this->moduleDir}/App/Views/index");
    }

    /**
     * Сохранение настроек
     */
    public function saveAction(): void
    {
        if ( ! $this->request->isPost()) {
            return;
        }
        $data   = $this->request->getPost();
        $record = ModuleSmartIVR::findFirst();

        if ($record === null) {
            $record = new ModuleSmartIVR();
        }

        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                case 'extension':
                    break;
                case 'useSSL':
                case 'debug_mode':
                    if (array_key_exists($key, $data)) {
                        $record->$key = ($data[$key] === 'on') ? '1' : '0';
                    } else {
                        $record->$key = '0';
                    }
                    break;
                default:
                    if ( ! array_key_exists($key, $data)) {
                        $record->$key = '';
                    } else {
                        $record->$key = $data[$key];
                    }
            }
        }

        if ($record->save() === false) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
        }
        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
    }

}