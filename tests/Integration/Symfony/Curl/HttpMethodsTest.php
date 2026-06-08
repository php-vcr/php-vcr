<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Util\TestHttpServer;

/**
 * PUT / DELETE / PATCH record/replay/passthrough for Symfony CurlHttpClient.
 * Record/replay skipped — same curl_getinfo limitation as #329.
 * Cassette names prefixed 'symfony-curl-methods-'.
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
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-methods-put.yml');
        $s1 = (new CurlHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-methods-put.yml');
        $s2 = (new CurlHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughPutRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new CurlHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount());
        $this->assertSame(200, $statusCode);
    }

    public function testDeleteRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-methods-delete.yml');
        $s1 = (new CurlHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-methods-delete.yml');
        $s2 = (new CurlHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughDeleteRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new CurlHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount());
        $this->assertSame(200, $statusCode);
    }

    public function testPatchRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-methods-patch.yml');
        $s1 = (new CurlHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-methods-patch.yml');
        $s2 = (new CurlHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughPatchRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new CurlHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount());
        $this->assertSame(200, $statusCode);
    }
}
