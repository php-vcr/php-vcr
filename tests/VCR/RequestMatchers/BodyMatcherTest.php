<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class BodyMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new BodyMatcher();

        $first = new Request('GET', 'http://example.com', array());
        $first->setBody('test');
        $second = new Request('GET', 'http://example.com', array());
        $second->setBody('test');

        $this->assertTrue($matcher->match($first, $second), 'Bodies should be equal');

        $first = new Request('GET', 'http://example.com', array());
        $first->setBody('test');
        $second = new Request('POST', 'http://example.com', array());
        $second->setBody('different');

        $this->assertFalse($matcher->match($first, $second), 'Bodies are different.');
    }
}
