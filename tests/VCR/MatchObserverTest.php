<?php

namespace VCR;

use lapistano\ProxyObject\ProxyBuilder;
use VCR\RequestMatchers\MethodMatcher;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class MatchObserverTest extends \PHPUnit_Framework_TestCase
{
    private $matchObserver;

    public function setUp() {
        $proxy = new ProxyBuilder('\VCR\MatchObserver');
        $this->matchObserver = $proxy
            ->setMethods(array('getClosestRequestHash'))
            ->getProxy();
        $this->matchObserver->startObserving();
    }

    public function testMismatchMessageReturnsBlankWhenNoMismatches() {
        $first = new Request('GET', 'http://example.com/common/path', array());
        $second = new Request('GET', 'http://example.com/common/path', array());
        $methodMatcher = new MethodMatcher();
        $methodMatcher->setMatchObserver($this->matchObserver);
        $methodMatcher->match($first, $second);
        $this->assertEmpty($this->matchObserver->getMismatchMessage());
    }

    public function testGetClosestRequestHashReturnsOnlyRequest() {
        $first = new Request('GET', 'http://example.com/common/path', array());
        $second = new Request('POST', 'http://example.com/common/path', array());
        $methodMatcher = new MethodMatcher();
        $methodMatcher->setMatchObserver($this->matchObserver);
        $methodMatcher->match($first, $second);
        $this->assertEquals($this->matchObserver->getClosestRequestHash(), $first->getIdentityHash());
    }

}
