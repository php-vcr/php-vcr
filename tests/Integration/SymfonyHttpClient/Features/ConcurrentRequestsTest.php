<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Features;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Tests concurrent/async requests with Symfony HttpClient.
 *
 * Similar to Guzzle's AsyncTest, this ensures VCR can handle multiple
 * concurrent requests without locking.
 */
class ConcurrentRequestsTest extends TestCase
{
    public const TEST_GET_URL = 'https://postman-echo.com/get';
    public const TEST_GET_URL_2 = 'https://postman-echo.com/get?foo=42';
    public const TEST_GET_URL_3 = 'https://postman-echo.com/get?bar=123';

    protected function setUp(): void
    {
        \VCR\VCR::configure()->setCassettePath(__DIR__.'/../../../fixtures/httpclient')
            ->enableLibraryHooks(['symfony_http_client'])
        ;
    }

    /**
     * Test that multiple concurrent requests don't lock each other.
     *
     * This solves potential race conditions when VCR handles multiple requests.
     *
     * @see https://github.com/php-vcr/php-vcr/issues/211
     */
    public function testMultipleConcurrentRequests(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('concurrent-requests.yml');

        $client = HttpClient::create();

        $responses = [];
        $responses[] = $client->request('GET', self::TEST_GET_URL);
        $responses[] = $client->request('GET', self::TEST_GET_URL_2);
        $responses[] = $client->request('GET', self::TEST_GET_URL_3);

        foreach ($client->stream($responses) as $response => $chunk) {
        }

        $data1 = json_decode($responses[0]->getContent(), true);
        $data2 = json_decode($responses[1]->getContent(), true);
        $data3 = json_decode($responses[2]->getContent(), true);

        $this->assertValidGETResponse($data1);
        $this->assertValidGETResponse($data2);
        $this->assertValidGETResponse($data3);

        $this->assertStringContainsString('postman-echo.com/get', $data1['url']);
        $this->assertStringContainsString('foo=42', $data2['url']);
        $this->assertStringContainsString('bar=123', $data3['url']);

        \VCR\VCR::turnOff();
    }

    /**
     * Test stream() method with multiple responses.
     */
    public function testStreamWithMultipleResponses(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('stream-multiple.yml');

        $client = HttpClient::create();

        $responses = [
            $client->request('GET', self::TEST_GET_URL),
            $client->request('GET', self::TEST_GET_URL_2),
        ];

        $completed = 0;
        foreach ($client->stream($responses) as $response => $chunk) {
            if ($chunk->isLast()) {
                ++$completed;
            }
        }

        $this->assertEquals(2, $completed, 'Both requests should complete');

        \VCR\VCR::turnOff();
    }

    protected function assertValidGETResponse(mixed $info): void
    {
        $this->assertIsArray($info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, 'API did not return any value.');
    }
}
