<?php

use VCR\VCR;

require_once __DIR__.'/../../../../vendor/autoload.php';

VCR::turnOn();
VCR::configure()->setCassettePath(__DIR__);
VCR::insertCassette('vcr.yaml');

require_once 'performRequest.php';
