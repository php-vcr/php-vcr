<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Util\TestHttpServer;

/**
 * Concurrent lazy requests for Symfony CurlHttpClient.
 * Record/replay skipped — same curl_getinfo limitation as #329.
 * Cassette name prefixed 'symfony-curl-async-'.
 */
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

    public function testConcurrentRequestsRecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();
        $client = new CurlHttpClient();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-async.yml');
        $r1 = $client->request('GET', self::$baseUrl.'/get');
        $r2 = $client->request('GET', self::$baseUrl.'/get?foo=42');
        $s1 = $r1->getStatusCode();
        $s2 = $r2->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 2, $countAfterRecord, 'Both requests must hit the server during recording');
        $this->assertSame(200, $s1);
        $this->assertSame(200, $s2);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-async.yml');
        $t1 = $client->request('GET', self::$baseUrl.'/get');
        $t2 = $client->request('GET', self::$baseUrl.'/get?foo=42');
        $u1 = $t1->getStatusCode();
        $u2 = $t2->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $u1);
        $this->assertSame(200, $u2);
    }
}
