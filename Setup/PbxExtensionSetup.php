<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 5 2019
 */

namespace Modules\ModuleSmartIVR\Setup;

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Core\System\Util;
use Modules\ModuleSmartIVR\Models\{ModuleSmartIVR};
use MikoPBX\Modules\Setup\PbxExtensionSetupBase;
use Throwable;

class PbxExtensionSetup extends PbxExtensionSetupBase
{

    /**
     * Создает структуру для хранения настроек модуля в своей модели
     * и заполняет настройки по-умолчанию если таблицы не было в системе
     * см (unInstallDB)
     *
     * Регистрирует модуль в PbxExtensionModules
     *
     * @return bool результат установки
     */
    public function installDB(): bool
    {
        $result = $this->createSettingsTableByModelsAnnotations();

        // Заполним начальные настройки
        if ($result) {
            $settings = ModuleSmartIVR::findFirst();
            if ( $settings === null) {
                $settings = new ModuleSmartIVR();
            }

            $module_extension    = Extensions::getNextFreeApplicationNumber();
            $settings->extension = $module_extension;
            $result              = $settings->save();
            $data                = Extensions::findFirst('number="' . $module_extension . '"');
            if ($data===null) {
                $data                    = new Extensions();
                $data->number            = $module_extension;
                $data->type              = 'MODULES';
                $data->callerid          = 'Module Smart IVR';
                $data->public_access     = 0;
                $data->show_in_phonebook = 1;
                $result                  = $result && $data->save();
            }
            if ( ! $result) {
                $this->message[] = 'Error: Failed to update table the Extensions table.';
            }
        }
        // Регаем модуль в PBX Extensions
        if ($result) {
            $result = $this->registerNewModule();
        }

        if ($result) {
            $this->transferOldSettings();
        }

        return $result;
    }

    /**
     *  Transfer settings from db to own module database
     */
    protected function transferOldSettings(): void
    {
        if ( ! $this->db->tableExists('m_ModuleSmartIVR')) {
            return;
        }
        $oldSettings = $this->db->fetchOne('Select * from m_ModuleSmartIVR', \Phalcon\Db\Enum::FETCH_ASSOC);

        $settings = ModuleSmartIVR::findFirst();
        if ( ! $settings) {
            $settings = new ModuleSmartIVR();
        }
        foreach ($settings as $key => $value) {
            if (isset($oldSettings[$key])) {
                $settings->$key = $oldSettings[$key];
            }
        }
        if ($settings->save()) {
            $this->db->dropTable('m_ModuleSmartIVR');
        } else {
            $this->messges[] = "Error on transfer old settings for $this->moduleUniqueID";
        }
    }

    /**
     * Удаляет запись о модуле из PbxExtensionModules
     * Удаляет свою модель
     *
     * @param  $keepSettings - оставляет таблицу с данными своей модели
     *
     * @return bool результат очистки
     */
    public function unInstallDB($keepSettings = false): bool
    {
        $result = true;
        // Удалим запись Extension для модуля
        try {
            $settings = ModuleSmartIVR::findFirst();
            if ($settings) {
                $data   = Extensions::findFirst('number="' . $settings->extension . '"');
                $result = $result && $data->delete();
            }
            // Если сохранились старые записи для модуля, тоже их подчистим
            $data = Extensions::find('callerid="Module Smart IVR"');
        } catch (\Exception $exception) {
            $data = Extensions::find('callerid="Module Smart IVR"');
        }
        if ($data) {
            $result = $result && $data->delete();
        }

        // Удалим допоплнительные таблицы
        if ($result) {
            $result = parent::unInstallDB($keepSettings);
        }

        return $result;
    }
}