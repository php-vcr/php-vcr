<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class MethodMatcherTest extends RequestMatcherTestCase
{
    private $matcher;

    public function setUp() {
        $this->matcher = new MethodMatcher();
    }

    public function testMatch()
    {
        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('GET', 'http://example.com', array());

        $this->assertTrue($this->matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('POST', 'http://example.com', array());

        $this->assertFalse($this->matcher->match($first, $second));
    }

    public function testGetMismatchMessage() {
        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('POST', 'http://example.com', array());

        $mismatchMessage = $this->matcher->getMismatchMessage($first, $second);
        $expectedMessage = $this->buildSimpleExpectedMessage('Method', 'GET', 'POST');
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}
