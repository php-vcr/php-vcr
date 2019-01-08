<?php

namespace VCR\Example;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Util\CurlException;

/**
 * Tests behaviour when an error occurs.
 */
class ErrorTest extends TestCase
{
    const TEST_GET_URL = 'http://localhost:9959';

    public function setUp()
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testConnectException()
    {
        $nonInstrumentedException = null;
        try {
            $client = new Client();
            $client->get(self::TEST_GET_URL);
        } catch (ConnectException $e) {
            $nonInstrumentedException = $e;
        }
        $this->assertNotNull($nonInstrumentedException);
        $catched = false;
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        try {
            $client = new Client();
            $client->get(self::TEST_GET_URL);
        } catch (ConnectException $e) {
            $catched = true;
            $this->assertEquals($e->getMessage(), $nonInstrumentedException->getMessage());
        }
        $this->assertTrue($catched);
        \VCR\VCR::turnOff();
    }

    protected function assertValidGETResponse($info)
    {
    }
}
