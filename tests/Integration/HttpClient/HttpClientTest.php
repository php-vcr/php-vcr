<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\HttpClient;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

class HttpClientTest extends TestCase
{
    public const TEST_GET_URL = 'https://httpbin.org/get';

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testGet(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = HttpClient::create();
        $response = $client->request('GET', self::TEST_GET_URL);

        $this->assertValidGETResponse(json_decode($response->getContent(), true));

        \VCR\VCR::turnOff();
    }

    protected function assertValidGETResponse(mixed $info): void
    {
        $this->assertIsArray($info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, 'API did not return any value.');
    }
}
