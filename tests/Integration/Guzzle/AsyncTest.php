<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

final class AsyncTest extends AbstractHttpServerIntegrationTestCase
{
    public function testAsyncLock(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = new Client();
        $promise = $client->getAsync(self::$baseUrl.'/get');
        $response = $promise->wait();
        $promise = $client->getAsync(self::$baseUrl.'/get?foo=42');
        $promise->wait();
        // Verifies two async requests on different URLs don't deadlock.
        // Regression for https://github.com/php-vcr/php-vcr/issues/211

        $this->assertValidGETResponse(\GuzzleHttp\json_decode($response->getBody()->getContents(), true));
    }

    protected function assertValidGETResponse(mixed $info): void
    {
        $this->assertIsArray($info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, 'API did not return any value.');
    }
}
