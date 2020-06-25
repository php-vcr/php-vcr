<?php

namespace VCR\Example;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Tests example request.
 */
class ExampleHttpClientTest extends TestCase
{
    const TEST_GET_URL = 'https://chew.pw/api/trbmb';
    const TEST_POST_URL = 'https://httpbin.org/post';
    const TEST_POST_BODY = '{"foo":"bar"}';

    public function setUp()
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testRequestGET()
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $originalRequest = $this->requestGET();
        $this->assertValidGETResponse($originalRequest);
        $interceptedRequest = $this->requestGET();
        $this->assertValidGETResponse($interceptedRequest);
        \VCR\VCR::turnOff();
    }

    public function testRequestPOST()
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $originalRequest = $this->requestPOST();
        $this->assertValidPOSTResponse($originalRequest);
        $interceptedRequest = $this->requestPOST();
        $this->assertValidPOSTResponse($interceptedRequest);
        \VCR\VCR::turnOff();
    }

    protected function requestGET()
    {
        $exampleClient = new ExampleHttpClient();

        $response = $exampleClient->get(self::TEST_GET_URL);

        return $response;
    }

    protected function requestPOST()
    {
        $exampleClient = new ExampleHttpClient();

        $response = $exampleClient->post(self::TEST_POST_URL, self::TEST_POST_BODY);
        unset($response['origin']);

        return $response;
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
        $this->assertIsArray($info, 'Response is not an array.');
        $this->assertArrayHasKey('0', $info, 'API did not return any value.');
    }

    protected function assertValidPOSTResponse($info)
    {
        $this->assertIsArray($info, 'Response is not an array.');
    }
}
