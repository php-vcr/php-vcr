<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Clients;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;

class TraceableHttpClientTest extends TestCase
{
    public const TEST_GET_URL = 'https://postman-echo.com/get';
    public const TEST_POST_URL = 'https://postman-echo.com/post';

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'))
            ->enableLibraryHooks(['symfony_http_client'])  // Enable code transformation hook!

        ;
    }

    public function testGet(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = HttpClient::create();
        $traceableClient = new TraceableHttpClient($client);
        $response = $traceableClient->request('GET', self::TEST_GET_URL);

        $this->assertValidGETResponse(json_decode($response->getContent(), true));

        \VCR\VCR::turnOff();
    }

    public function testPostWithEmptyBody(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = HttpClient::create();
        $traceableClient = new TraceableHttpClient($client);
        $response = $traceableClient->request('POST', self::TEST_POST_URL);

        $data = json_decode($response->getContent(), true);
        $this->assertValidGETResponse($data);
        $this->assertArrayHasKey('json', $data, 'API did not return POST data');

        \VCR\VCR::turnOff();
    }

    public function testPostWithJson(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');

        $client = HttpClient::create();
        $traceableClient = new TraceableHttpClient($client);
        $response = $traceableClient->request('POST', self::TEST_POST_URL, [
            'json' => [
                'test' => true,
            ],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertValidGETResponse($data);
        $this->assertArrayHasKey('json', $data, 'API did not return POST data');

        \VCR\VCR::turnOff();
    }

    protected function assertValidGETResponse(mixed $info): void
    {
        $this->assertIsArray($info, 'Response is not an array.');
        $this->assertArrayHasKey('url', $info, 'API did not return any value.');
    }
}
