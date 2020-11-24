#!/usr/bin/php
<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 10 2018
 */

require_once 'Globals.php';
$ivr    = new Modules\ModuleSmartIVR\Lib\AGICallLogic();
$ivr->startIVR();

exit (0);