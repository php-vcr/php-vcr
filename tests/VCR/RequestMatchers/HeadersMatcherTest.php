<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class HeadersMatcherTest extends RequestMatcherTestCase
{
    private $matcher;

    public function setUp() {
        $this->matcher = new HeadersMatcher();
    }

    public function testMatchingHeaders()
    {
        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));

        $this->assertTrue($this->matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Nothing'));

        $this->assertFalse($this->matcher->match($first, $second));
    }

    public function testHeaderMatchingDisallowsMissingHeaders()
    {
        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything', 'MyHeader' => 'value'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));

        $this->assertFalse($this->matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything', 'MyHeader' => 'value'));

        $this->assertFalse($this->matcher->match($first, $second));
    }

    public function testHeaderMatchingIgnoresEmptyVals()
    {
        $first = new Request('GET', 'http://example.com', array('Accept' => null, 'Content-Type' => 'application/json'));
        $second = new Request('GET', 'http://example.com', array('AnotherHeader' => null, 'Content-Type' => 'application/json'));

        $this->assertTrue($this->matcher->match($first, $second));
    }

    public function testGetMismatchMessage() {
        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything', 'MyHeader' => 'value'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));

        $mismatchMessage = $this->matcher->getMismatchMessage($first, $second);
        $expectedMessage = $this->buildSimpleExpectedMessage('Headers', print_r($first->getHeaders(), true), print_r($second->getHeaders(), true));
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}
