<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 2 2019
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
                'number_of_repeat', [
                'maxlength'    => 2,
                'style'        => 'width: 80px;',
                'defaultValue' => 1,
            ]
            )
        );

        // UseSSL
        $checkAr = ['value' => null];
        if ($entity->useSSL) {
            $checkAr = ['checked' => 'checked', 'value' => null];
        }
        $this->add(new Check('useSSL', $checkAr));

        // Library
        $arrLibraryType = [
            '1.0' => $this->translation->_('module_smivr_LibraryVer1'),
            '2.0' => $this->translation->_('module_smivr_LibraryVer2'),
        ];

        $library = new Select(
            'library_1c', $arrLibraryType, [
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
        $extension = new Select(
            'failover_extension', $options['extensions'], [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => false,
            'class'    => 'ui selection dropdown search forwarding-select',
        ]
        );
        $this->add($extension);

        // Timeout Extension
        $extension = new Select(
            'timeout_extension', $options['extensions'], [
            'using'    => [
                'id',
                'name',
            ],
            'useEmpty' => false,
            'class'    => 'ui selection dropdown search forwarding-select',
        ]
        );
        $this->add($extension);

        // debug_mode
        $checkAr = ['value' => null];
        if ($entity->debug_mode) {
            $checkAr = ['checked' => 'checked', 'value' => null];
        }
        $this->add(new Check('debug_mode', $checkAr));
    }
}