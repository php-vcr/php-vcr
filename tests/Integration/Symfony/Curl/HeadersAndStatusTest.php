<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Util\TestHttpServer;

/**
 * Custom headers and non-200 status codes for Symfony CurlHttpClient.
 * Record/replay skipped — same curl_getinfo limitation as #329.
 * Cassette names prefixed 'symfony-curl-headers-'.
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
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-headers-custom.yml');
        $s1 = (new CurlHttpClient())->request('GET', self::$baseUrl.'/get', [
            'headers' => ['X-Custom-Header' => 'test-value'],
        ])->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-headers-custom.yml');
        $s2 = (new CurlHttpClient())->request('GET', self::$baseUrl.'/get', [
            'headers' => ['X-Custom-Header' => 'test-value'],
        ])->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testStatus404RecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-headers-404.yml');
        $s1 = (new CurlHttpClient())->request('GET', self::$baseUrl.'/status/404')->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(404, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-headers-404.yml');
        $s2 = (new CurlHttpClient())->request('GET', self::$baseUrl.'/status/404')->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(404, $s2);
    }

    public function testStatus500RecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-headers-500.yml');
        $s1 = (new CurlHttpClient())->request('GET', self::$baseUrl.'/status/500')->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(500, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-headers-500.yml');
        $s2 = (new CurlHttpClient())->request('GET', self::$baseUrl.'/status/500')->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(500, $s2);
    }
}
