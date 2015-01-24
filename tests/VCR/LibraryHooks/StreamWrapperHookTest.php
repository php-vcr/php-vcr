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

    public function streamContexts()
    {
        $test = $this;

        return array(
            'header' => array(
                array('header' => 'Content-Type: application/json'),
                function (Request $request) use ($test) {
                   $test->assertEquals('application/json', $request->getHeader('Content-Type'));
                }
            ),

            'header with trailing newline' => array(
                array('header' => "Content-Type: application/json\r\n"),
                function (Request $request) use ($test) {
                    $test->assertEquals('application/json', $request->getHeader('Content-Type'));
                }
            ),

            'multiple headers' => array(
                array('header' => "Content-Type: application/json\r\nContent-Length: 123"),
                function (Request $request) use ($test) {
                    $test->assertEquals('application/json', $request->getHeader('Content-Type'));
                    $test->assertEquals('123', $request->getHeader('Content-Length'));
                }
            ),

            'user_agent' => array(
                array('user_agent' => 'example'),
                function (Request $request) use ($test) {
                    $test->assertEquals('example', $request->getHeader('User-Agent'));
                }
            ),

            'content' => array(
                array('content' => 'example'),
                function (Request $request) use ($test) {
                    $test->assertEquals('example', $request->getBody());
                }
            ),

            'follow_location' => array(
                array('follow_location' => '0'),
                function (Request $request) use ($test) {
                    $test->assertEquals(false, $request->getCurlOption(CURLOPT_FOLLOWLOCATION));
                }
            ),

            'max_redirects' => array(
                array('max_redirects' => '2'),
                function (Request $request) use ($test) {
                    $test->assertEquals('2', $request->getCurlOption(CURLOPT_MAXREDIRS));
                }
            ),

            'timeout' => array(
                array('timeout' => '100'),
                function (Request $request) use ($test) {
                    $test->assertEquals('100', $request->getCurlOption(CURLOPT_TIMEOUT));
                }
            )
        );
    }

    /**
     *
     * @dataProvider streamContexts
     * @param $context
     * @param $testCallback
     */
    public function testStreamHttpContext($context, $testCallback) {
        $streamWrapper = new StreamWrapperHook();
        $streamWrapper->context = stream_context_create(array(
            'http' => $context
        ));

        $streamWrapper->enable($testCallback);

        $streamWrapper->stream_open('http://example.com', 'd', 0, $test);
    }
}
