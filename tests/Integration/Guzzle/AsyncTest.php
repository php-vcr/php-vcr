<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

final class AsyncTest extends TestCase
{
    public const TEST_GET_URL = 'https://httpbin.org/get';
    public const TEST_GET_URL_2 = 'https://httpbin.org/get?foo=42';

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testAsyncLock(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = new Client();
        $promise = $client->getAsync(self::TEST_GET_URL);
        $response = $promise->wait();
        $promise = $client->getAsync(self::TEST_GET_URL_2);
        $promise->wait();
        // Let's check that we can perform 2 async request on different URLs without locking.
        // Solves https://github.com/php-vcr/php-vcr/issues/211

        $this->assertValidGETResponse(\GuzzleHttp\json_decode($response->getBody()->getContents(), true));

        \VCR\VCR::turnOff();
    }

    protected function assertValidGETResponse(mixed $info): void
    {
        $this->assertIsArray($info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, 'API did not return any value.');
    }
}
