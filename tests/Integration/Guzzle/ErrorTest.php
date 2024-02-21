<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

final class ErrorTest extends TestCase
{
    public const TEST_GET_URL = 'http://localhost:9959';

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testConnectException(): void
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
}
