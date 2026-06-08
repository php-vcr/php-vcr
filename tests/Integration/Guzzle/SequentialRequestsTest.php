<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Tests\Util\TestHttpServer;

/**
 * Three sequential requests in one cassette — regression for issue #432
 * (stale curl handle state between sequential requests).
 * Cassette prefix 'guzzle-seq-' avoids VCRFactory cache collisions.
 */
final class SequentialRequestsTest extends TestCase
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

    public function testSequentialRequestsRecordAndReplay(): void
    {
        $countBefore = $this->server()->getRequestCount();
        $client = new Client();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-seq.yml');
        $r1 = $client->get(self::$baseUrl.'/get');
        $r2 = $client->get(self::$baseUrl.'/get?page=2');
        $r3 = $client->get(self::$baseUrl.'/get?page=3');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 3, $countAfterRecord, 'All three requests must hit the server');
        $this->assertSame(200, $r1->getStatusCode());
        $this->assertSame(200, $r2->getStatusCode());
        $this->assertSame(200, $r3->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-seq.yml');
        $s1 = $client->get(self::$baseUrl.'/get');
        $s2 = $client->get(self::$baseUrl.'/get?page=2');
        $s3 = $client->get(self::$baseUrl.'/get?page=3');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s1->getStatusCode());
        $this->assertSame(200, $s2->getStatusCode());
        $this->assertSame(200, $s3->getStatusCode());
    }
}
