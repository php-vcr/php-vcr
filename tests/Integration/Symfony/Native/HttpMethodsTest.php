<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Util\TestHttpServer;

/**
 * PUT / DELETE / PATCH record/replay/passthrough for Symfony NativeHttpClient.
 * Record/replay skipped — NativeHttpClient sends headers as array. See #329.
 * Cassette names prefixed 'symfony-native-methods-'.
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
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-methods-put.yml');
        $s1 = (new NativeHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-methods-put.yml');
        $s2 = (new NativeHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughPutRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new NativeHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $statusCode);
    }

    public function testDeleteRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-methods-delete.yml');
        $s1 = (new NativeHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-methods-delete.yml');
        $s2 = (new NativeHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughDeleteRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new NativeHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $statusCode);
    }

    public function testPatchRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-methods-patch.yml');
        $s1 = (new NativeHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-methods-patch.yml');
        $s2 = (new NativeHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughPatchRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new NativeHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $statusCode);
    }
}
