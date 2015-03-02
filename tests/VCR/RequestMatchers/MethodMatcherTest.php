<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class MethodMatcherTest extends \PHPUnit_Framework_TestCase
{
	private $methodMatcher;

	public function setUp() {
		$this->methodMatcher = new MethodMatcher();
	}

    public function testMatch()
    {
    	$first = new Request('GET', 'http://example.com', array());
        $second = new Request('GET', 'http://example.com', array());

        $this->assertTrue($this->methodMatcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('POST', 'http://example.com', array());

        $this->assertFalse($this->methodMatcher->match($first, $second));
    }

    public function testGetMismatchMessage() {
    	$first = new Request('GET', 'http://example.com', array());
        $second = new Request('POST', 'http://example.com', array());

        $mismatchMessage = $this->methodMatcher->getMismatchMessage($first, $second);
        $expectedMessage = " Stored request: Method: GET\n"
                         . "Current request: Method: POST";
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}
