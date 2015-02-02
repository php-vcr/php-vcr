<?php
namespace VCR\Util;

use VCR\Request;

class StreamHelperTest extends \PHPUnit_Framework_TestCase {

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
     * @dataProvider streamContexts
     * @param $context
     * @param $testCallback
     */
    public function testStreamHttpContext($context, $testCallback) {
        $context = stream_context_create(array(
            'http' => $context
        ));

        $request = StreamHelper::createRequestFromStreamContext($context, 'http://example.com');
        $testCallback($request);
    }
}
