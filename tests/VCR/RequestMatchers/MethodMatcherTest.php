<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class MethodMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new MethodMatcher();

        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('GET', 'http://example.com', array());

        $this->assertTrue($matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('POST', 'http://example.com', array());

        $this->assertFalse($matcher->match($first, $second));
    }
}
