<?php

namespace VCR\Example;

use GuzzleHttp\Client;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Tests example request.
 */
class AsyncTest extends TestCase
{
    const TEST_GET_URL = 'https://api.chew.pro/trbmb';
    const TEST_GET_URL_2 = 'https://api.chew.pro/trbmb?foo=42';

    public function setUp()
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testAsyncLock()
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = new Client();
        $promise = $client->getAsync(self::TEST_GET_URL);
        $response = $promise->wait();
        $promise = $client->getAsync(self::TEST_GET_URL_2);
        $promise->wait();
        // Let's check that we can perform 2 async request on different URLs without locking.
        // Solves https://github.com/php-vcr/php-vcr/issues/211

        $this->assertValidGETResponse(\GuzzleHttp\json_decode($response->getBody()));

        \VCR\VCR::turnOff();
    }

    protected function assertValidGETResponse($info)
    {
        $this->assertInternalType('array', $info, 'Response is not an array.');
        $this->assertArrayHasKey('0', $info, 'API did not return any value.');
    }
}
