<?php

if (!file_exists(__DIR__ . "/../vendor/autoload.php")) {
    die(
        "\n[ERROR] You need to run composer before running the test suite.\n".
        "To do so run the following commands:\n".
        "    curl -s http://getcomposer.org/installer | php\n".
        "    php composer.phar install\n\n"
    );
}

$loader = require_once __DIR__ . '/../vendor/autoload.php';

$loader->addClassMap(
    array(
        'VCR\\VCR_TestCase' => __DIR__ . '/VCR/VCR_TestCase.php',
    )
);

\VCR\VCR::configure()
    ->enableLibraryHooks(array('curl_rewrite', 'soap'))
    ->setBlackList(array('Soap/FilterTest'));
\VCR\VCR::turnOn();
\VCR\VCR::turnOff();

