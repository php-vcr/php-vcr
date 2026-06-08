<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Tests\Util\TestHttpServer;

/**
 * Custom request headers and non-200 status codes record+replay via CurlHook.
 *
 * Uses a single Client per test method so Guzzle reuses handles via curl_reset()
 * instead of curl_init(), avoiding the stale $responses issue in CurlHook (#432).
 * Cassette names prefixed 'guzzle-headers-' to avoid VCRFactory cache collisions.
 */
final class HeadersAndStatusTest extends TestCase
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

    protected function tearDown(): void
    {
        \VCR\VCR::turnOff();
    }

    private function server(): TestHttpServer
    {
        $server = self::$server;
        $this->assertNotNull($server);

        return $server;
    }

    public function testCustomRequestHeadersRecordAndReplay(): void
    {
        $client = new Client();
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-headers-custom.yml');
        $r1 = $client->get(self::$baseUrl.'/get', ['headers' => ['X-Custom-Header' => 'test-value']]);
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-headers-custom.yml');
        $r2 = $client->get(self::$baseUrl.'/get', ['headers' => ['X-Custom-Header' => 'test-value']]);
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $r2->getStatusCode());
    }

    public function testStatus404RecordAndReplay(): void
    {
        $client = new Client(['http_errors' => false]);
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-headers-404.yml');
        $r1 = $client->get(self::$baseUrl.'/status/404');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(404, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-headers-404.yml');
        $r2 = $client->get(self::$baseUrl.'/status/404');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(404, $r2->getStatusCode());
    }

    public function testStatus500RecordAndReplay(): void
    {
        $client = new Client(['http_errors' => false]);
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-headers-500.yml');
        $r1 = $client->get(self::$baseUrl.'/status/500');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(500, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-headers-500.yml');
        $r2 = $client->get(self::$baseUrl.'/status/500');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(500, $r2->getStatusCode());
    }
}
