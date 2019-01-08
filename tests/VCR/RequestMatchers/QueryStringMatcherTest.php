<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class QueryStringMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new QueryStringMatcher();

        $first = new Request('GET', 'http://example.com/search?query=test', array());
        $second = new Request('GET', 'http://example.com/search?query=test', array());

        $this->assertTrue($matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com/search?query=first', array());
        $second = new Request('GET', 'http://example.com/search?query=second', array());

        $this->assertFalse($matcher->match($first, $second));
    }
}
