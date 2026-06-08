<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Tests\Util\TestHttpServer;

/**
 * Basic GET + POST record/replay/passthrough via CurlHook (default Guzzle handler).
 *
 * A single Client instance is shared across record and replay phases so that
 * Guzzle reuses curl handles via curl_reset() rather than curl_init(). This is
 * important because VCR's curlReset() clears the stale $responses entry for the
 * handle ID, preventing curlMultiExec() from skipping the handle during replay.
 * Without this, PHP may reuse an integer ID (spl_object_id recycling) from the
 * previous phase and curlMultiExec() would treat the handle as already processed.
 * See issue #432 for the underlying CurlHook bug.
 *
 * Cassette names prefixed 'guzzle-basic-' to avoid VCRFactory cache collisions.
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
        $client = new Client();
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-basic-get.yml');
        $r1 = $client->get(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-basic-get.yml');
        $r2 = $client->get(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $r2->getStatusCode());
    }

    public function testPassthroughGetRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $response = (new Client())->get(self::$baseUrl.'/get');

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $client = new Client();
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-basic-post.yml');
        $r1 = $client->post(self::$baseUrl.'/post', ['body' => 'hello=world']);
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame(200, $r1->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-basic-post.yml');
        $r2 = $client->post(self::$baseUrl.'/post', ['body' => 'hello=world']);
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $r2->getStatusCode());
    }

    public function testPassthroughPostRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();

        $response = (new Client())->post(self::$baseUrl.'/post', ['body' => 'hello=world']);

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame(200, $response->getStatusCode());
    }
}
