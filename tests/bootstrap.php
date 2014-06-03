<?php

$loader = require __DIR__ . "/../vendor/autoload.php";
$loader->addPsr4('CWMetricsReport\\', __DIR__.'/CWMetricsReport');

date_default_timezone_set('UTC');