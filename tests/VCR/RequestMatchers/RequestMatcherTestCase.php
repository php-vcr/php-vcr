<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class RequestMatcherTestCase extends \PHPUnit_Framework_TestCase
{
	public function buildSimpleExpectedMessage($prefix, $firstMessage, $secondMessage) {
		$expectedMessage = " Stored request: {$prefix}: {$firstMessage}\n"
                         . "Current request: {$prefix}: {$secondMessage}";
        return $expectedMessage;
	}
}
