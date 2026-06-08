<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Tests\Util\TestHttpServer;

/**
 * PUT / DELETE / PATCH record+replay via CurlHook.
 *
 * Uses a single Client per test method so Guzzle reuses handles via curl_reset()
 * instead of curl_init(), avoiding the stale $responses issue in CurlHook (#432).
 * Cassette names prefixed 'guzzle-methods-' to avoid VCRFactory cache collisions.
 */
final class HttpMethodsTest extends TestCase
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

    public function testPutRequestRecordAndReplay(): void
    {
        $client = new Client();
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-methods-put.yml');
        $r1 = $client->put(self::$baseUrl.'/put', ['body' => 'data=1']);
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-methods-put.yml');
        $r2 = $client->put(self::$baseUrl.'/put', ['body' => 'data=1']);
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $r2->getStatusCode());
    }

    public function testDeleteRequestRecordAndReplay(): void
    {
        $client = new Client();
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-methods-delete.yml');
        $r1 = $client->delete(self::$baseUrl.'/delete');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-methods-delete.yml');
        $r2 = $client->delete(self::$baseUrl.'/delete');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $r2->getStatusCode());
    }

    public function testPatchRequestRecordAndReplay(): void
    {
        $client = new Client();
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-methods-patch.yml');
        $r1 = $client->patch(self::$baseUrl.'/patch', ['body' => 'field=value']);
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-methods-patch.yml');
        $r2 = $client->patch(self::$baseUrl.'/patch', ['body' => 'field=value']);
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $r2->getStatusCode());
    }
}
