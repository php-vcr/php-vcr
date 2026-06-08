<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Native;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Tests\Util\TestHttpServer;

/**
 * Verifies VCR interception of bare curl_* calls via CurlHook.
 * Cassette names prefixed 'native-curl-' to avoid VCRFactory cache collisions.
 */
final class CurlTest extends TestCase
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

    public function testGetRequestRecordAndReplay(): void
    {
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-curl-get.yml');
        $s1 = $this->curlGet(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-curl-get.yml');
        $s2 = $this->curlGet(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughGetRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = $this->curlGet(self::$baseUrl.'/get');

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $statusCode);
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-curl-post.yml');
        $s1 = $this->curlPost(self::$baseUrl.'/post', 'hello=world');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-curl-post.yml');
        $s2 = $this->curlPost(self::$baseUrl.'/post', 'hello=world');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughPostRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = $this->curlPost(self::$baseUrl.'/post', 'hello=world');

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $statusCode);
    }

    private function curlGet(string $url): int
    {
        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $statusCode;
    }

    private function curlPost(string $url, string $body): int
    {
        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $body);
        curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $statusCode;
    }
}
