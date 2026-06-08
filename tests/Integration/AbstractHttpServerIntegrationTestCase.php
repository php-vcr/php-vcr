<?php

declare(strict_types=1);

namespace VCR\Tests\Integration;

use VCR\Tests\Util\TestHttpServer;

abstract class AbstractHttpServerIntegrationTestCase extends AbstractIntegrationTestCase
{
    private static ?TestHttpServer $server = null;
    protected static string $baseUrl = '';

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

    protected function server(): TestHttpServer
    {
        $server = self::$server;
        $this->assertNotNull($server);

        return $server;
    }

    /**
     * @param \Closure(): int $perform
     */
    protected function recordAndReplay(string $cassette, \Closure $perform, int $expectedStatus = 200): void
    {
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette($cassette);
        $statusRecord = $perform();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertSame($expectedStatus, $statusRecord);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette($cassette);
        $statusReplay = $perform();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame($expectedStatus, $statusReplay);
    }

    /**
     * @param \Closure(): int $perform
     */
    protected function assertPassthrough(\Closure $perform, int $expectedStatus = 200): void
    {
        $countBefore = $this->server()->getRequestCount();
        $status = $perform();
        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertSame($expectedStatus, $status);
    }
}
