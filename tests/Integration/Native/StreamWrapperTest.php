<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Native;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Tests\Util\TestHttpServer;

/**
 * Verifies VCR interception of native file_get_contents / stream_context_create
 * calls via StreamWrapperHook.
 * Cassette names prefixed 'native-sw-' to avoid VCRFactory cache collisions.
 */
final class StreamWrapperTest extends TestCase
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
        \VCR\VCR::insertCassette('native-sw-get.yml');
        $b1 = file_get_contents(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertNotFalse($b1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-sw-get.yml');
        $b2 = file_get_contents(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertNotFalse($b2);
    }

    public function testPassthroughGetRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $body = file_get_contents(self::$baseUrl.'/get');

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertNotFalse($body);
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => 'hello=world',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            ],
        ]);

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-sw-post.yml');
        $b1 = file_get_contents(self::$baseUrl.'/post', false, $context);
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertNotFalse($b1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-sw-post.yml');
        $b2 = file_get_contents(self::$baseUrl.'/post', false, $context);
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertNotFalse($b2);
    }

    public function testPassthroughPostRequest(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => 'hello=world',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            ],
        ]);

        $countBefore = $this->server()->getRequestCount();

        $body = file_get_contents(self::$baseUrl.'/post', false, $context);

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertNotFalse($body);
    }
}
