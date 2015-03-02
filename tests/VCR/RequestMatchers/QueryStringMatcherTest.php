<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class QueryStringMatcherTest extends RequestMatcherTestCase
{
    private $matcher;

    public function setUp() {
        $this->matcher = new QueryStringMatcher();
    }

    public function testMatch()
    {
        $first = new Request('GET', 'http://example.com/search?query=test', array());
        $second = new Request('GET', 'http://example.com/search?query=test', array());

        $this->assertTrue($this->matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com/search?query=first', array());
        $second = new Request('GET', 'http://example.com/search?query=second', array());

        $this->assertFalse($this->matcher->match($first, $second));
    }

    public function testGetMismatchMessage() {
        $first = new Request('GET', 'http://example.com/search?query=first', array());
        $second = new Request('GET', 'http://example.com/search?query=second', array());

        $mismatchMessage = $this->matcher->getMismatchMessage($first, $second);
        $expectedMessage = $this->buildSimpleExpectedMessage('Query string', 'query=first', 'query=second');
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}
