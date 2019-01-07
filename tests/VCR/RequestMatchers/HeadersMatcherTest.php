<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class HeadersMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new HeadersMatcher();

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));

        $this->assertTrue($matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Nothing'));

        $this->assertFalse($matcher->match($first, $second));
    }

    public function testHeaderMatchingDisallowsMissingHeaders()
    {
        $matcher = new HeadersMatcher();

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything', 'MyHeader' => 'value'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));

        $this->assertFalse($matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything', 'MyHeader' => 'value'));

        $this->assertFalse($matcher->match($first, $second));
    }

    public function testHeaderMatchingAllowsEmptyVals()
    {
        $matcher = new HeadersMatcher();

        $first = new Request('GET', 'http://example.com', array('Accept' => null, 'Content-Type' => 'application/json'));
        $second = new Request('GET', 'http://example.com', array('Accept' => null, 'Content-Type' => 'application/json'));

        $this->assertTrue($matcher->match($first, $second));
    }
}
