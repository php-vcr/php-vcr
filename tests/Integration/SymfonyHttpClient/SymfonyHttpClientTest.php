<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient;

use Assert\Assertion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\TraceableHttpClient;
use VCR\Tests\Integration\SymfonyHttpClient\Support\JsonPlaceholderClient;
use VCR\VCR;

/**
 * Tests for Symfony HttpClient compatibility with VCR.
 *
 * This test suite validates that VCR correctly intercepts and records/replays
 * HTTP requests made through various Symfony HttpClient implementations.
 *
 * Uses code transformation to automatically wrap Symfony HttpClient instances.
 */
class SymfonyHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        VCR::configure()
            ->setCassettePath(__DIR__.'/../../fixtures/symfony_httpclient')
            ->enableLibraryHooks(['symfony_http_client'])
            ->setMode('once');

        VCR::turnOn();
    }

    protected function tearDown(): void
    {
        VCR::eject();
        VCR::turnOff();
    }

    public function testCurlHttpClientGet(): void
    {
        VCR::insertCassette('curl_get.yml');

        $httpClient = new CurlHttpClient([
            'timeout' => 30,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $client = new JsonPlaceholderClient($httpClient);
        $result = $client->getPost(1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('body', $result);
    }

    public function testCurlHttpClientPost(): void
    {
        VCR::insertCassette('curl_post.yml');

        $httpClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $client = new JsonPlaceholderClient($httpClient);

        $result = $client->createPost([
            'title' => 'Test Post',
            'body' => 'Testing VCR with CurlHttpClient',
            'userId' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testCurlHttpClientPut(): void
    {
        VCR::insertCassette('curl_put.yml');

        $httpClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $client = new JsonPlaceholderClient($httpClient);

        $result = $client->updatePost(1, [
            'title' => 'Updated Post',
            'body' => 'Updated body',
            'userId' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
    }

    public function testCurlHttpClientDelete(): void
    {
        VCR::insertCassette('curl_delete.yml');

        $httpClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $client = new JsonPlaceholderClient($httpClient);

        $result = $client->deletePost(1);

        $this->assertTrue($result);
    }

    public function testNativeHttpClientGet(): void
    {
        VCR::insertCassette('native_get.yml');

        $httpClient = new NativeHttpClient([
            'timeout' => 30,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $client = new JsonPlaceholderClient($httpClient);
        $result = $client->getPost(1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
    }

    public function testNativeHttpClientPost(): void
    {
        VCR::insertCassette('native_post.yml');

        $httpClient = new NativeHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $client = new JsonPlaceholderClient($httpClient);

        $result = $client->createPost([
            'title' => 'Test with NativeHttpClient',
            'body' => 'Testing POST with native streams',
            'userId' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testNativeHttpClientPut(): void
    {
        VCR::insertCassette('native_put.yml');

        $httpClient = new NativeHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $client = new JsonPlaceholderClient($httpClient);

        $result = $client->updatePost(1, [
            'title' => 'Updated with NativeHttpClient',
            'body' => 'Testing PUT with native streams',
            'userId' => 1,
        ]);

        $this->assertIsArray($result);
    }

    public function testNativeHttpClientDelete(): void
    {
        VCR::insertCassette('native_delete.yml');

        $httpClient = new NativeHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $client = new JsonPlaceholderClient($httpClient);

        $result = $client->deletePost(1);

        $this->assertTrue($result);
    }

    public function testMockHttpClientGet(): void
    {
        VCR::turnOff();

        $mockResponse = [
            'userId' => 1,
            'id' => 1,
            'title' => 'Mocked Title',
            'body' => 'Mocked Body',
        ];

        $jsonBody = json_encode($mockResponse);
        Assertion::string($jsonBody, 'json_encode should not fail with valid array');

        $httpClient = new MockHttpClient([
            new MockResponse($jsonBody, [
                'http_code' => 200,
                'response_headers' => ['Content-Type: application/json'],
            ]),
        ]);

        $client = new JsonPlaceholderClient($httpClient);
        $result = $client->getPost(1);

        $this->assertEquals($mockResponse, $result);

        VCR::turnOn();
    }

    public function testTraceableHttpClientGet(): void
    {
        VCR::insertCassette('traceable_get.yml');

        $baseClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $traceableClient = new TraceableHttpClient($baseClient);

        $client = new JsonPlaceholderClient($traceableClient);
        $result = $client->getPost(1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);

        $traces = $traceableClient->getTracedRequests();
        $this->assertNotEmpty($traces);
    }
}
