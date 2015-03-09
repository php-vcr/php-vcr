<?php

namespace VCR;

use lapistano\ProxyObject\ProxyBuilder;
use VCR\RequestMatchers\MethodMatcher;
use VCR\RequestMatchers\UrlMatcher;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class MismatchExplainerTest extends \PHPUnit_Framework_TestCase
{
    private $mismatchExplainer;

    public function setUp() {
        $proxy = new ProxyBuilder('\VCR\MismatchExplainer');
        $this->mismatchExplainer = $proxy
            ->setMethods(array('getClosestRequestHash'))
            ->getProxy();
        $this->mismatchExplainer->startObserving();
    }

    protected function runMatch($first, $second, $matcherName = '') {
        $matcher = null;
        switch ($matcherName) {
            case 'url':
                $matcher = new UrlMatcher();
            default:
                $matcher = new MethodMatcher();
        }
        $matcher->setMismatchExplainer($this->mismatchExplainer);
        $matcher->match($first, $second);
    }

    protected function runMatches($storedRequests, $second, array $matcherNames = array('')) {
        $storedRequestsArray = $storedRequests;
        if (!is_array($storedRequests)) {
            $storedRequestsArray = array($storedRequestsArray);
        }
        foreach($storedRequestsArray as $storedRequest) {
            foreach ($matcherNames as $matcherName) {
                $this->runMatch($storedRequest, $second, $matcherName);
            }
        }
    }

    protected function assertCorrectHash($actual, $expected, $message = null) {
        if ($message == null) {
            $message = "The closest match did not pick the closest request";
        }
        $this->assertEquals($actual, $expected, $message);
    }

    public function testMismatchMessageReturnsBlankWhenNoMismatches() {
        $currentRequest = new Request('GET', 'http://example.com/common/path', array());
        $storedRequest1 = new Request('GET', 'http://example.com/common/path', array());
        $this->runMatch($storedRequest1, $currentRequest);
        $this->assertEmpty($this->mismatchExplainer->getMismatchMessage());
    }

    public function testGetClosestRequestHashReturnsOnlyRequest() {
        $currentRequest = new Request('GET', 'http://example.com/common/path', array());
        $storedRequest1 = new Request('POST', 'http://example.com/common/path', array());
        $this->runMatch($storedRequest1, $currentRequest);
        $this->assertCorrectHash($this->mismatchExplainer->getClosestRequestHash(), $storedRequest1->getIdentityHash());
    }

    public function testGetClosestRequestHashReturnsExactMatch() {
        $currentRequest = new Request('GET', 'http://example.com/common/path', array());
        $storedRequest1 = new Request('GET', 'http://example.com/common/path', array());
        $storedRequest2 = new Request('POST', 'http://example.com/common/path', array());
        $this->runMatches(array($storedRequest1, $storedRequest2), $currentRequest);
        $this->assertCorrectHash($this->mismatchExplainer->getClosestRequestHash(), $storedRequest2->getIdentityHash());
    }

    public function testGetClosestRequestHashReturnsFirstWhenTwoDifferBySameAmount() {
        $currentRequest = new Request('GET', 'http://example.com/common/path', array());
        $storedRequest1 = new Request('GDT', 'http://example.com/common/path', array());
        $storedRequest2 = new Request('GET', 'gttp://example.com/common/path', array());
        $this->runMatches($storedRequest1, $currentRequest, array('method', 'url'));
        $this->runMatches($storedRequest2, $currentRequest, array('method', 'url'));
        $this->assertCorrectHash($this->mismatchExplainer->getClosestRequestHash(), $storedRequest1->getIdentityHash());
    }

    // public function testGetClosestRequestHashReturnsSecondWhenItHasFewerMarkedMismatches() {
    //     $currentRequest = new Request('GET', 'http://example.com/common/path', array());
    //     $storedRequest1 = new Request('GDT', 'gttp://example.com/common/path', array());
    //     $storedRequest2 = new Request('GET', 'gttp://example.com/common/path', array());
    //     $this->runMatches(array($storedRequest1, $storedRequest2), $currentRequest, array('method', 'url'));
    //     $this->assertCorrectHash($this->mismatchExplainer->getClosestRequestHash(), $storedRequest2->getIdentityHash());
    // }

    // public function testGetClosestRequestHashReturnsSecondWhenMinimallyCloser() {
    //     $currentRequest = new Request('GET', 'http://example.com/common/path', array());
    //     $storedRequest1 = new Request('HEY', 'http://example.com/common/path', array());
    //     $storedRequest2 = new Request('GQT', 'gttp://example.com/common/path', array());
    //     $this->runMatch($storedRequest1, $currentRequest);
    //     $this->runMatch($storedRequest2, $currentRequest, 'url');
    //     $this->assertEquals($this->mismatchExplainer->getClosestRequestHash(), $storedRequest2->getIdentityHash());
    // }

}
