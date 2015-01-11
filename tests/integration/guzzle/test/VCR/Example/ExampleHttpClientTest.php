<?php

namespace VCR\Example;

use org\bovigo\vfs\vfsStream;

/**
 * Tests example request.
 */
class ExampleHttpClientTest extends \PHPUnit_Framework_TestCase
{
    const TEST_URL = 'http://httpbin.org/get';

    protected $ignoreHeaders = array(
        'Accept',
        'Connect-Time',
        'Total-Route-Time',
        'X-Request-Id',
    );

    public function setUp()
    {
        // Configure virtual filesystem.
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testRequestGETDirect()
    {
        $this->assertValidResponse($this->requestGET());
    }

    public function testRequestGETIntercepted()
    {
        $this->assertValidResponse($this->requestGETIntercepted());
    }

    public function testRequestGETDirectEqualsIntercepted()
    {
        $this->assertEquals($this->requestGET(), $this->requestGETIntercepted());
    }

    public function testRequestGETInterceptedIsRepeatable()
    {
        $this->assertEquals($this->requestGETIntercepted(), $this->requestGETIntercepted());
    }

    protected function requestGET()
    {
        $exampleClient = new ExampleHttpClient();

        $response = $exampleClient->get(self::TEST_URL);
        foreach ($this->ignoreHeaders as $header) {
            unset($response['headers'][$header]);
        }

        return $response;
    }

    protected function requestGETIntercepted()
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $info = $this->requestGET();
        \VCR\VCR::turnOff();

        return $info;
    }

    protected function assertValidResponse($info)
    {
        $this->assertTrue(is_array($info), 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, "Key 'url' not found.");
        $this->assertEquals(self::TEST_URL, $info['url'], "Value for key 'url' wrong.");
        $this->assertArrayHasKey('headers', $info, "Key 'headers' not found.");
        $this->assertTrue(is_array($info['headers']), 'Headers is not an array.');
    }
}
