<?php

/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2024 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */
namespace Modules\ModuleSmartIVR\App\Forms;

use Phalcon\Forms\Element\Check;
use Phalcon\Forms\Element\Numeric;
use Phalcon\Forms\Element\Password;
use Phalcon\Forms\Element\Select;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Form;

class ModuleSmartIVRForm extends Form
{
    public function initialize($entity = null, $options = null)
    {
        $this->add(new Text('server1chost'));
        $this->add(new Numeric('server1cport'));
        $this->add(new Text('login'));
        $this->add(new Password('secret'));
        $this->add(new Text('database'));
        $this->add(
            new Numeric(
                'number_of_repeat',
                [
                'maxlength'    => 2,
                'style'        => 'width: 80px;',
                'defaultValue' => 1,
                ]
            )
        );

        // UseSSL
        $this->addCheckBox('useSSL', intval($entity->useSSL) === 1);

        // Library
        $arrLibraryType = [
            '1.0' => $this->translation->_('module_smivr_LibraryVer1'),
            '2.0' => $this->translation->_('module_smivr_LibraryVer2'),
            '5.0' => $this->translation->_('module_smivr_LibraryVer5'),
        ];

        $library = new Select(
            'library_1c',
            $arrLibraryType,
            [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => false,
            'value'    => $entity->library_1c,
            'class'    => 'ui selection dropdown library-type-select',
            ]
        );
        $this->add($library);

        // FailOver Extension
        if ($options['useExtensionSelector'] ?? false) {
            // New version (2025.1.1+): Use text input, ExtensionSelector will be initialized via JS
            $extension = new Text(
                'failover_extension',
                [
                    'class' => 'extension-selector',
                    'data-value' => $entity->failover_extension ?? '',
                ]
            );
        } else {
            // Legacy version: Use traditional Select dropdown
            $extension = new Select(
                'failover_extension',
                $options['extensions'],
                [
                    'using'    => [
                        'id',
                        'name',
                    ],
                    'useEmpty' => false,
                    'class'    => 'ui selection dropdown search forwarding-select',
                ]
            );
        }
        $this->add($extension);

        // Timeout Extension
        if ($options['useExtensionSelector'] ?? false) {
            // New version (2025.1.1+): Use text input, ExtensionSelector will be initialized via JS
            $extension = new Text(
                'timeout_extension',
                [
                    'class' => 'extension-selector',
                    'data-value' => $entity->timeout_extension ?? '',
                ]
            );
        } else {
            // Legacy version: Use traditional Select dropdown
            $extension = new Select(
                'timeout_extension',
                $options['extensions'],
                [
                    'using'    => [
                        'id',
                        'name',
                    ],
                    'useEmpty' => false,
                    'class'    => 'ui selection dropdown search forwarding-select',
                ]
            );
        }
        $this->add($extension);

        $this->addCheckBox('debug_mode', intval($entity->debug_mode) === 1);

        $this->add(new Numeric('last_responsible_time'));
        $this->add(new Numeric('last_responsible_duration'));
    }

    /**
     * Adds a checkbox to the form field with the given name.
     * Can be deleted if the module depends on MikoPBX later than 2024.3.0
     *
     * @param string $fieldName The name of the form field.
     * @param bool $checked Indicates whether the checkbox is checked by default.
     * @param string $checkedValue The value assigned to the checkbox when it is checked.
     * @return void
     */
    public function addCheckBox(string $fieldName, bool $checked, string $checkedValue = 'on'): void
    {
        $checkAr = ['value' => null];
        if ($checked) {
            $checkAr = ['checked' => $checkedValue,'value' => $checkedValue];
        }
        $this->add(new Check($fieldName, $checkAr));
    }
}
