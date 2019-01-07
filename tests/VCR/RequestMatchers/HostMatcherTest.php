<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class HostMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new HostMatcher();

        $first = new Request('GET', 'http://example.com/common/path', array());
        $second = new Request('GET', 'http://example.com/common/path', array());

        $this->assertTrue($matcher->match($first, $second));

        $first = new Request('GET', 'http://example.com/first/path', array());
        $second = new Request('GET', 'http://elpmaxe.com/second/path', array());

        $this->assertFalse($matcher->match($first, $second));
    }
}
