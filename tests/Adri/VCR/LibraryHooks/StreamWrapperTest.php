<?php

namespace Adri\PHPVCR\LibraryHooks;

/**
 * Test if intercepting http/https using stream wrapper works.
 */
class StreamWrapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Adri\PHPVCR\LibraryHooks\StreamWrapper
     */
    private $streamWrapper;

    public function setUp()
    {
    }

    public function testEnable()
    {
        $testClass = $this;
        $this->streamWrapper = new StreamWrapper(function($request) use($testClass) {
            // var_dump($request);
        });
        $this->streamWrapper->enable();
    }

    public function testDisable()
    {
        $testClass = $this;
        $this->streamWrapper = new StreamWrapper(function($request) use($testClass) {
            // var_dump($request);
        });
        $this->streamWrapper->disable();
    }

    public function tearDown()
    {
    }
}
