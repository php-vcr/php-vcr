<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Tests\Util\TestHttpServer;

final class AsyncTest extends TestCase
{
    private static ?TestHttpServer $server = null;
    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        self::$server = TestHttpServer::start();
        self::$baseUrl = self::$server->getBaseUrl();
    }

    public static function tearDownAfterClass(): void
    {
        if (null !== self::$server) {
            self::$server->stop();
            self::$server = null;
        }
    }

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testAsyncLock(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = new Client();
        $promise = $client->getAsync(self::$baseUrl.'/get');
        $response = $promise->wait();
        $promise = $client->getAsync(self::$baseUrl.'/get?foo=42');
        $promise->wait();
        // Let's check that we can perform 2 async request on different URLs without locking.
        // Solves https://github.com/php-vcr/php-vcr/issues/211

        $this->assertValidGETResponse(\GuzzleHttp\json_decode($response->getBody()->getContents(), true));

        \VCR\VCR::turnOff();
    }

    protected function assertValidGETResponse(mixed $info): void
    {
        $this->assertIsArray($info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, 'API did not return any value.');
    }
}
