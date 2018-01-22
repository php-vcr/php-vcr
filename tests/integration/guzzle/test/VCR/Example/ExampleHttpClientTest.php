<?php

namespace VCR\Example;

use org\bovigo\vfs\vfsStream;

/**
 * Tests example request.
 */
class ExampleHttpClientTest extends \PHPUnit_Framework_TestCase
{
    const TEST_GET_URL = 'http://httpbin.org/get';
    const TEST_POST_URL = 'http://httpbin.org/post';
    const TEST_POST_BODY = '{"foo":"bar"}';
    const TEST_TIMEOUT_URL = 'http://httpbin.org/delay/120';

    protected $ignoreHeaders = array(
        'Accept',
        'Connect-Time',
        'Total-Route-Time',
        'X-Request-Id',
    );

    public function setUp()
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testRequestGETDirect()
    {
        $this->assertValidGETResponse($this->requestGET());
    }

    public function testRequestGETIntercepted()
    {
        $this->assertValidGETResponse($this->requestGETIntercepted());
    }

    public function testRequestGETDirectEqualsIntercepted()
    {
        $this->assertEquals($this->requestGET(), $this->requestGETIntercepted());
    }

    public function testRequestGETInterceptedIsRepeatable()
    {
        $this->assertEquals($this->requestGETIntercepted(), $this->requestGETIntercepted());
    }

    public function testRequestPOSTDirect()
    {
        $this->assertValidPOSTResponse($this->requestPOST());
    }

    public function testRequestPOSTIntercepted()
    {
        $this->assertValidPOSTResponse($this->requestPOSTIntercepted());
    }

    public function testRequestPOSTDirectEqualsIntercepted()
    {
        $this->assertEquals($this->requestPOST(), $this->requestPOSTIntercepted());
    }

    public function testRequestPOSTInterceptedIsRepeatable()
    {
        $this->assertEquals($this->requestPOSTIntercepted(), $this->requestPOSTIntercepted());
    }

    public function testFailedRequest()
    {
        $interceptedEx = null;
        try {
            $this->requestGETIntercepted(self::TEST_TIMEOUT_URL, array('timeout' => 1));
            $this->fail("expected a request exception");
        } catch (\Exception $e) {
            $interceptedEx = $e;
        }

        $notInterceptedEx = null;
        try {
            $this->requestGET(self::TEST_TIMEOUT_URL, array('timeout' => 1));
            $this->fail("expected a request exception");
        } catch (\Exception $e) {
            $notInterceptedEx = $e;
        }

        $this->assertEquals(
            get_class($notInterceptedEx),
            get_class($interceptedEx));
        $this->assertEquals(
            $this->normaliseTimeoutExceptionMessage($notInterceptedEx->getMessage()),
            $this->normaliseTimeoutExceptionMessage($interceptedEx->getMessage()));
        $this->assertEquals(
            $notInterceptedEx->getCode(),
            $interceptedEx->getCode());
    }

    /**
     * The timeout exception message includes the exact time taken, which can
     * vary a bit, but we don't want the test to fail due to timing jitter
     */
    private function normaliseTimeoutExceptionMessage($msg) {
        return preg_replace('/after \d+ milliseconds/', 'after nnn milliseconds', $msg);
    }

    protected function requestGET($url = self::TEST_GET_URL, $options = array())
    {
        $exampleClient = new ExampleHttpClient();

        $response = $exampleClient->get($url, $options);
        foreach ($this->ignoreHeaders as $header) {
            unset($response['headers'][$header]);
        }

        return $response;
    }

    protected function requestPOST()
    {
        $exampleClient = new ExampleHttpClient();

        $response = $exampleClient->post(self::TEST_POST_URL, self::TEST_POST_BODY);
        foreach ($this->ignoreHeaders as $header) {
            unset($response['headers'][$header]);
        }

        return $response;
    }

    protected function requestGETIntercepted($url = self::TEST_GET_URL, $options = array())
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $info = $this->requestGET($url, $options);
        \VCR\VCR::turnOff();

        return $info;
    }

    protected function requestPOSTIntercepted()
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $info = $this->requestPOST();
        \VCR\VCR::turnOff();

        return $info;
    }

    protected function assertValidGETResponse($info)
    {
        $this->assertInternalType('array', $info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, "Key 'url' not found.");
        $this->assertEquals(self::TEST_GET_URL, $info['url'], "Value for key 'url' wrong.");
        $this->assertArrayHasKey('headers', $info, "Key 'headers' not found.");
        $this->assertInternalType('array', $info['headers'], 'Headers is not an array.');
    }

    protected function assertValidPOSTResponse($info)
    {
        $this->assertInternalType('array', $info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, "Key 'url' not found.");
        $this->assertEquals(self::TEST_POST_URL, $info['url'], "Value for key 'url' wrong.");
        $this->assertArrayHasKey('headers', $info, "Key 'headers' not found.");
        $this->assertInternalType('array', $info['headers'], 'Headers is not an array.');
        $this->assertEquals(self::TEST_POST_BODY, $info['data'], 'Correct request body was not sent.');
    }
}
