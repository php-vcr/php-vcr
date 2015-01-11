<?php
require_once __DIR__ . '/../../../../vendor/autoload.php';
$loader = require_once 'vendor/autoload.php';

/**
 * @var \Composer\Autoload\ClassLoader
 */
$loader->addClassMap(array(
    'VCR\\Example\\ExampleHttpClient' => 'ExampleHttpClient.php'
));

\VCR\VCR::turnOn();
\VCR\VCR::turnOff();
