<?php
require_once __DIR__ . '/../../../../vendor/autoload.php';
$loader = require_once 'vendor/autoload.php';

/**
 * @var \Composer\Autoload\ClassLoader
 */
$loader->addClassMap(array(
    'VCR\\Example\\Guzzle\\GithubProject' => 'GithubProject.php'
));

\VCR\VCR::configure()->setCassettePath(__DIR__ . '/fixtures');
\VCR\VCR::turnOn();
\VCR\VCR::turnOff();
