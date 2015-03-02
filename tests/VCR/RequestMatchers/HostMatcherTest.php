<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class HostMatcherTest extends RequestMatcherTestCase
{
    private $matcher;

    public function setUp() {
        $this->matcher = new HostMatcher();
    }

    public function testMatch()
    {
        $first = new Request('GET', 'http://example.com/common/path', array());
        $second = new Request('GET', 'http://example.com/common/path', array());

        $this->assertTrue($this->matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com/first/path', array());
        $second = new Request('GET', 'http://elpmaxe.com/second/path', array());

        $this->assertFalse($this->matcher->match($first, $second));
    }

    public function testGetMismatchMessage() {
        $first = new Request('GET', 'http://example.com/first/path', array());
        $second = new Request('GET', 'http://elpmaxe.com/second/path', array());

        $mismatchMessage = $this->matcher->getMismatchMessage($first, $second);
        $expectedMessage = $this->buildSimpleExpectedMessage('Host', 'example.com', 'elpmaxe.com');
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}
