<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Util\TestHttpServer;

/**
 * Sequential multi-request record/replay for Symfony NativeHttpClient.
 * Record/replay skipped — NativeHttpClient sends headers as array. See #329.
 * Cassette name prefixed 'symfony-native-seq-'.
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
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $countBefore = $this->server()->getRequestCount();
        $client = new NativeHttpClient();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-seq.yml');
        $s1 = $client->request('GET', self::$baseUrl.'/get')->getStatusCode();
        $s2 = $client->request('GET', self::$baseUrl.'/get?page=2')->getStatusCode();
        $s3 = $client->request('GET', self::$baseUrl.'/get?page=3')->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 3, $countAfterRecord, 'All three requests must hit the server');
        $this->assertSame(200, $s1);
        $this->assertSame(200, $s2);
        $this->assertSame(200, $s3);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-seq.yml');
        $t1 = $client->request('GET', self::$baseUrl.'/get')->getStatusCode();
        $t2 = $client->request('GET', self::$baseUrl.'/get?page=2')->getStatusCode();
        $t3 = $client->request('GET', self::$baseUrl.'/get?page=3')->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $t1);
        $this->assertSame(200, $t2);
        $this->assertSame(200, $t3);
    }
}
