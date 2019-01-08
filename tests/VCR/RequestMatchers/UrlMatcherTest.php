<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class UrlMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new UrlMatcher();

        $first = new Request('GET', 'http://example.com/common/path', array());
        $second = new Request('GET', 'http://example.com/common/path', array());

        $this->assertTrue($matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com/first/path', array());
        $second = new Request('GET', 'http://example.com/second/path', array());

        $this->assertFalse($matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com/second', array());
        $second = new Request('GET', 'http://example.com/second/path', array());

        $this->assertFalse($matcher->match($first, $second));
    }
}
