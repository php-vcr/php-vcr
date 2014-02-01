<?php

namespace VCR\LibraryHooks;

/**
 * Test if intercepting http/https using stream wrapper works.
 */
class StreamWrapperHookTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \VCR\LibraryHooks\StreamWrapperHook
     */
    private $streamWrapper;

    public function testEnable()
    {
        $testClass = $this;
        $this->streamWrapper = new StreamWrapperHook();
        $this->streamWrapper->enable(function ($request) use ($testClass) {
        });
    }

    public function testDisable()
    {
        $testClass = $this;
        $this->streamWrapper = new StreamWrapperHook();
        $this->streamWrapper->disable();
    }

    public function tearDown()
    {
    }
}
