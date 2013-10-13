<?php

namespace VCR\LibraryHooks;

/**
 * Test if intercepting http/https using stream wrapper works.
 */
class StreamWrapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \VCR\LibraryHooks\StreamWrapper
     */
    private $streamWrapper;

    public function setUp()
    {
    }

    public function testEnable()
    {
        $testClass = $this;
        $this->streamWrapper = new StreamWrapper();
        $this->streamWrapper->enable(function($request) use($testClass) {
        });
    }

    public function testDisable()
    {
        $testClass = $this;
        $this->streamWrapper = new StreamWrapper();
        $this->streamWrapper->disable();
    }

    public function tearDown()
    {
    }
}
