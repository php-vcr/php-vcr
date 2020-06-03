<?php

error_reporting(E_ALL & ~E_DEPRECATED); // Ignore deprecation messages for PHP 7.4

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die(
        "\n[ERROR] You need to run composer before running the test suite.\n" .
        "To do so run the following commands:\n" .
        "    curl -s http://getcomposer.org/installer | php\n" .
        "    php composer.phar install\n\n"
    );
}

require_once __DIR__ . '/../vendor/autoload.php';

\VCR\VCR::turnOn();
\VCR\VCR::turnOff();
