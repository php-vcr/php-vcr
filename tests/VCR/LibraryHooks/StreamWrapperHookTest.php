<?php

namespace VCR\LibraryHooks;

use Exception;
use VCR\Request;
use VCR\Response;
use VCR\VCR;
use VCR\Videorecorder;

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

        $hook->disable();
    }

    /**
     * Check that the StreamWrapperHook does the best it can on a
     * connection failure.
     *
     * Currently the behaviour does differ between with the hook
     * and without the hook. See implementation notes in
     * VCR\Util\HttpClient::send
     */
    public function testConnectionError()
    {
        $errWithoutHook = null;
        try {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 1
                )
            ));
            file_get_contents('http://httpbin.org/delay/120', null, $context);

            $this->fail("expected exception");
        } catch (Exception $e) {
            $errWithoutHook = $e;
        }

        $errWithHook = null;
        VCR::turnOn();
        VCR::insertCassette(str_replace('\\', '/', __CLASS__));
        try {
            $context = stream_context_create(array(
                'http' => array(
                    'timeout' => 1
                )
            ));
            file_get_contents('http://httpbin.org/delay/120', null, $context);

            $this->fail("expected exception");
        } catch (Exception $e) {
            $errWithHook = $e;
        }
        VCR::turnOff();

        // Here, we'd like to be able to do:
        // $this->assertEquals($errWithoutHook, $errWithHook);

        // but, as you can see:
        $this->assertEquals(
            'file_get_contents(http://httpbin.org/delay/120): failed to open stream: HTTP request failed! ',
            $errWithoutHook->getMessage());
        $this->assertEquals(
            '28: Operation timed out after 1000 milliseconds with 0 bytes received',
            $errWithHook->getMessage());

        $this->assertEquals(
            'PHPUnit_Framework_Error_Warning',
            get_class($errWithoutHook));
        $this->assertEquals(
            'VCR\Util\HttpClientException',
            get_class($errWithHook));
    }
}
