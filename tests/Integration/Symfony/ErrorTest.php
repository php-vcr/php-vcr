<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Tests behaviour when an error occurs.
 */
class ErrorTest extends TestCase
{
    public const TEST_GET_URL = 'http://localhost:9959';

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testConnectException(): void
    {
        $nonInstrumentedException = null;
        try {
            $client = HttpClient::create();
            $response = $client->request('GET', self::TEST_GET_URL);
            $response->getHeaders();
        } catch (TransportExceptionInterface $e) {
            $nonInstrumentedException = $e;
        }
        self::assertNotNull($nonInstrumentedException);
        $catched = false;
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        try {
            $client = HttpClient::create();
            $response = $client->request('GET', self::TEST_GET_URL);
            $response->getHeaders();
        } catch (TransportExceptionInterface $e) {
            $catched = true;
            self::assertEquals($e->getMessage(), $nonInstrumentedException->getMessage());
        }
        self::assertTrue($catched);
        \VCR\VCR::turnOff();
    }
}
