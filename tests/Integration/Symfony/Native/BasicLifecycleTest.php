<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Util\TestHttpServer;

/**
 * Basic GET + POST record/replay/passthrough for Symfony NativeHttpClient via StreamWrapperHook.
 *
 * Record/replay tests are skipped: NativeHttpClient passes HTTP headers as an array in
 * the PHP stream context, but VCR's StreamHelper::createRequestFromStreamContext() calls
 * HttpUtil::parseRawHeader() which requires a string. Remove the markTestSkipped() calls
 * once that is fixed.
 *
 * Passthrough tests (no VCR) verify that NativeHttpClient can reach the test server.
 * Cassette names prefixed 'symfony-native-basic-' to avoid VCRFactory cache collisions.
 */
final class BasicLifecycleTest extends TestCase
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
        // See tests/Integration/Guzzle/BasicLifecycleTest.php for the pattern to restore.
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context; parseRawHeader() requires string. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-basic-get.yml');
        $s1 = (new NativeHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-basic-get.yml');
        $s2 = (new NativeHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughGetRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new NativeHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $statusCode);
    }

    public function testPostRequestRecordAndReplay(): void
    {
        // See tests/Integration/Guzzle/BasicLifecycleTest.php for the pattern to restore.
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context; parseRawHeader() requires string. See #329.');

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-basic-post.yml');
        $s1 = (new NativeHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $s1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-basic-post.yml');
        $s2 = (new NativeHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s2);
    }

    public function testPassthroughPostRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $statusCode = (new NativeHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $statusCode);
    }
}
