<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use VCR\Tests\Integration\AbstractIntegrationTestCase;

final class ErrorTest extends AbstractIntegrationTestCase
{
    public const TEST_GET_URL = 'http://localhost:9959';

    public function testConnectException(): void
    {
        $nonInstrumentedException = null;
        try {
            $client = new Client();
            $client->get(self::TEST_GET_URL);
        } catch (ConnectException $e) {
            $nonInstrumentedException = $e;
        }
        $this->assertNotNull($nonInstrumentedException);
        $catched = false;
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        try {
            $client = new Client();
            $client->get(self::TEST_GET_URL);
        } catch (ConnectException $e) {
            $catched = true;
            $this->assertEquals(
                $this->normalizeConnectExceptionMessage($e->getMessage()),
                $this->normalizeConnectExceptionMessage($nonInstrumentedException->getMessage())
            );
        }
        $this->assertTrue($catched);
    }

    private function normalizeConnectExceptionMessage(string $message): string
    {
        return (string) preg_replace('/ after \d+ ms/', '', $message);
    }
}
