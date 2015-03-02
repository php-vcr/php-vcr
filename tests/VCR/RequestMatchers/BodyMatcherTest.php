<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class BodyMatcherTest extends RequestMatcherTestCase
{
    private $matcher;

    public function setUp() {
        $this->matcher = new BodyMatcher();
    }

    public function testMatch()
    {
        $first = new Request('POST', 'http://example.com', array());
        $first->setBody('test');
        $second = new Request('POST', 'http://example.com', array());
        $second->setBody('test');

        $this->assertTrue($this->matcher->match($first, $second), 'Bodies should be equal');

        $first = new Request('POST', 'http://example.com', array());
        $first->setBody('test');
        $second = new Request('POST', 'http://example.com', array());
        $second->setBody('different');

        $this->assertFalse($this->matcher->match($first, $second), 'Bodies should be different.');
    }

    public function testGetMismatchMessage() {
        $first = new Request('POST', 'http://example.com', array());
        $first->setBody('test');
        $second = new Request('POST', 'http://example.com', array());
        $second->setBody('different');

        $mismatchMessage = $this->matcher->getMismatchMessage($first, $second);
        $expectedMessage = $this->buildSimpleExpectedMessage('Body', 'test', 'different');
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}