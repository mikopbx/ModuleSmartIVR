<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 9 2019
 */

namespace Modules\ModuleSmartIVR\Lib;

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\ModelsBase;
use MikoPBX\Core\System\Util;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\Core\System\PBX;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleSmartIVR\Models\ModuleSmartIVR;

/**
 * Class SmartIVR
 */
class SmartIVRConf extends ConfigClass
{

    /**
     * Возвращает включения в контекст internal
     *
     * @return string
     */
    public function getIncludeInternal(): string
    {
        $conf     = '';
        $settings = ModuleSmartIVR::findFirst();
        if ($settings !== null && ! empty($settings->extension)) {
            // Включаем контексты.
            $conf = 'include => module_smartivr ' . PHP_EOL;
        }

        return $conf;
    }

    /**
     * Генерация дополнительных контекстов.
     *
     * @return string
     */
    public function extensionGenContexts(): string
    {
        $app_ext_conf = '';
        $settings     = ModuleSmartIVR::findFirst();
        if ($settings !== null && ! empty($settings->extension)) {
            $app_ext_conf = PHP_EOL . '[module_smartivr]' . PHP_EOL;
            $app_ext_conf .= 'exten => ' . $settings->extension . ',1,ExecIf($["${CHANNEL(channeltype)}" == "Local"]?Gosub(set_orign_chan,s,1))' . PHP_EOL;
            $app_ext_conf .= 'same => n,Gosub(dial_app,${EXTEN},1)' . PHP_EOL;
            $app_ext_conf .= "same => n,AGI(SmartIVR_AGI.php)" . PHP_EOL;
        }

        return $app_ext_conf;
    }

    /**
     * Кастомизация входящего контекста для конкретного маршрута.
     *
     * @param $rout_number
     *
     * @return string
     */
    public function generateIncomingRoutBeforeDial($rout_number): string
    {
        $conf = "";
        $settings     = ModuleSmartIVR::findFirst();
        if($settings && $settings->server1chost === '0.0.0.0'){
            $conf = "same => n,AGI({$this->moduleDir}/agi-bin/SmartIVR_AGI.php)".PHP_EOL;
        }
        return $conf;
    }

    /**
     *  Process CoreAPI requests under root rights
     *
     * @param array $request
     *
     * @return PBXApiResult An object containing the result of the API call.
     * @throws \Exception
     */
    public function moduleRestAPICallback(array $request): PBXApiResult
    {
        $res            = new PBXApiResult();
        $res->processor = __METHOD__;
        $action         = strtoupper($request['action']);
        switch ($action) {
            case 'CHECK':
                // Проверка работы сервисов, выполняется при обновлении статуса или сохрании настроек
                $ivr = new AGICallLogic();
                $res = $ivr->selfTest();
                break;
            default:
                $res->success    = false;
                $res->messages[] = 'API action not found in moduleRestAPICallback ModuleSmartIVR';
        }

        return $res;
    }

    /**
     * Process enable action in web interface
     *
     * @return bool
     */
    public function onBeforeModuleEnable(): bool
    {
        $result = true;
        $record = ModuleSmartIVR::findFirst();
        if ($record !== null && ! empty($record->redirection_settings)) {
            // Try to restore settings
            try {
                $relatedLinks = unserialize($record->redirection_settings, ['allowed_classes' => false]);
                if (!is_array($relatedLinks)) {
                    $this->messages[]='Old redirection settings are lost.';
                    return false;
                }
                foreach ($relatedLinks as $savedRecord) {
                    if (!is_array($savedRecord)) {
                        continue;
                    }
                    // Try to find object
                    if (!class_exists($savedRecord['className'])){
                        continue;
                    }
                    $obj = new $savedRecord['className']();
                    if (!$obj instanceof ModelsBase){
                        continue;
                    }
                    $idField = $obj->getIdentityFieldName();
                    $obj = $savedRecord['className']::findFirst("{$idField}={$savedRecord['objectID']}");
                    if ($obj!==null && get_class($obj) !== get_class($record)){
                        $obj->writeAttribute($savedRecord['referencedField'], $record->extension);
                        $result               = $result && $obj->save();
                        $this->messages['changedObjects'][] = $obj->getRepresent(true);
                    }

                }
            } catch (\Exception $exception) {
                $this->messages[]='onBeforeModuleEnable error on restore related object';
            }
        }
        return $result;
    }

    /**
     * Process disable action in web interface
     *
     * @return bool
     */
    public function onBeforeModuleDisable(): bool
    {
        $result = true;
        $changedObjects = [];
        $record         = ModuleSmartIVR::findFirst();
        if ($record !== null && !empty($record->extension)) {
            $extension = Extensions::findFirst("number ='{$record->extension}'");
            if ($extension !== null) {
                $relatedLinks = $extension->getRelatedLinks();
                foreach ($relatedLinks as $relation) {
                    $obj = $relation['object'];
                    if (get_class($obj) !== get_class($record)) {
                        // Change link to this module on failover_extension
                        $referenceField       = $relation['referenceField'];
                        $obj->$referenceField = $record->failover_extension;
                        $result         = $result && $obj->save();
                        $objId = $obj->getIdentityFieldName();
                        $changedObjects[]     = [
                            "className" => get_class($obj),
                            "referencedField"=>$referenceField,
                            "objectID"=>$obj->$objId,
                        ];
                        $this->messages['changedObjects'][] = $obj->getRepresent(true);
                    }
                }
            }
            if (count($changedObjects) > 0) {
                $record->redirection_settings = serialize( $changedObjects);
                $result                       = $result && $record->save();
            } else {
                $record->redirection_settings = '';
                $result                       = $result && $record->save();
            }
        }

        return $result;
    }

    /**
     * Process after disable action in web interface
     *
     * @return void
     */
    public function onAfterModuleDisable(): void
    {
        PBX::dialplanReload();
    }

    /**
     * Process enable action
     *
     * @return void
     */
   public function onAfterModuleEnable(): void
   {
       PBX::dialplanReload();
   }

}

