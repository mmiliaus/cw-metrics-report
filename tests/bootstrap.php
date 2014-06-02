<?php

/*
 * This file is part of the CWMetricsReport package.
 *
 * (c) Martynas Miliauskas <mmiliauskass@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$loader = require __DIR__ . "/../vendor/autoload.php";
$loader->addPsr4('CWMetricsReport\\', __DIR__.'/CWMetricsReport');

date_default_timezone_set('UTC');