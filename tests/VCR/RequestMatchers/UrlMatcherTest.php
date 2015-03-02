<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class UrlMatcherTest extends RequestMatcherTestCase
{
	private $matcher;

	public function setUp() {
		$this->matcher = new UrlMatcher();
	}

    public function testMatch()
    {
    	$first = new Request('GET', 'http://example.com', array());
        $second = new Request('GET', 'http://example.com', array());

        $this->assertTrue($this->matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('GET', 'http://example2.com', array());

        $this->assertFalse($this->matcher->match($first, $second));
    }

    public function testGetMismatchMessage() {
    	$first = new Request('GET', 'http://example.com', array());
        $second = new Request('GET', 'http://example2.com', array());

        $mismatchMessage = $this->matcher->getMismatchMessage($first, $second);
        $expectedMessage = $this->buildSimpleExpectedMessage('URL', 'http://example.com', 'http://example2.com');
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}
