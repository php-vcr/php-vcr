<?php

namespace VCR\RequestMatchers;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VCR\Request;

class CompositeRequestMatcherTest extends TestCase
{

    public function testMatch(): void
    {
        $yesMatcher = new class implements RequestMatcherInterface {
            public function match(Request $storedRequest, Request $request): bool
            {
                return true;
            }
        };
        $noMatcher = new class implements RequestMatcherInterface {
            public function match(Request $storedRequest, Request $request): bool
            {
                return false;
            }
        };

        $request = new Request('GET', 'http://example.com', array());

        $matcher = new CompositeRequestMatcher([$yesMatcher, $yesMatcher]);
        $this->assertTrue($matcher->match($request, $request));

        $matcher = new CompositeRequestMatcher([$yesMatcher, $noMatcher]);
        $this->assertFalse($matcher->match($request, $request));
    }

    public function testException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CompositeRequestMatcher(['foo']);
    }
}
