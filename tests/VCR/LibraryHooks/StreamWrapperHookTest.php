<?php

namespace VCR\LibraryHooks;

use VCR\Request;
use VCR\Response;

/**
 * Test if intercepting http/https using stream wrapper works.
 */
class StreamWrapperHookTest extends \PHPUnit_Framework_TestCase
{
    public function testEnable()
    {
        $streamWrapper = new StreamWrapperHook();

        $testClass = $this;
        $streamWrapper->enable(function ($request) use ($testClass) {
            $testClass->assertInstanceOf('\VCR\Request', $request);
        });
        $this->assertTrue($streamWrapper->isEnabled());
    }

    public function testDisable()
    {
        $streamWrapper = new StreamWrapperHook();
        $streamWrapper->disable();
        $this->assertFalse($streamWrapper->isEnabled());
    }

    public function testSeek()
    {
        $hook = new StreamWrapperHook();
        $hook->enable(function ($request) {
            return new Response(200, array(), 'A Test');
        });
        $hook->stream_open('http://example.com', 'r', 0, $openedPath);

        $this->assertFalse($hook->stream_seek(-1, SEEK_SET));
        $this->assertTrue($hook->stream_seek(0, SEEK_SET));
        $this->assertEquals('A', $hook->stream_read(1));

        $this->assertFalse($hook->stream_seek(-1, SEEK_CUR));
        $this->assertTrue($hook->stream_seek(1, SEEK_CUR));
        $this->assertEquals('Test', $hook->stream_read(4));

        $this->assertFalse($hook->stream_seek(-1000, SEEK_END));
        $this->assertTrue($hook->stream_seek(-4, SEEK_END));
        $this->assertEquals('Test', $hook->stream_read(4));

        // invalid whence
        $this->assertFalse($hook->stream_seek(0, -1));
    }
}
