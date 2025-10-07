<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use VCR\VCRHttpClient;

final class VCRHttpClientTest extends TestCase
{
    public function testImplementsHttpClientInterface(): void
    {
        $baseClient = new MockHttpClient();
        $client = new VCRHttpClient($baseClient);

        $this->assertInstanceOf(
            \Symfony\Contracts\HttpClient\HttpClientInterface::class,
            $client,
            'VCRHttpClient should implement HttpClientInterface.'
        );
    }

    public function testRequestPassesThroughWhenVCRNotActive(): void
    {
        $expectedBody = 'test response';
        $mockResponse = new MockResponse($expectedBody);
        $baseClient = new MockHttpClient($mockResponse);
        $client = new VCRHttpClient($baseClient);

        $response = $client->request('GET', 'https://example.com');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedBody, $response->getContent());
    }

    public function testWithOptionsReturnsNewInstance(): void
    {
        $baseClient = new MockHttpClient();
        $client = new VCRHttpClient($baseClient);

        $newClient = $client->withOptions(['timeout' => 30]);

        $this->assertNotSame($client, $newClient, 'withOptions() should return a new instance.');
        $this->assertInstanceOf(VCRHttpClient::class, $newClient);
    }

    public function testStreamPassesThroughNonMockResponses(): void
    {
        $mockResponse1 = new MockResponse('test1');
        $mockResponse2 = new MockResponse('test2');
        $baseClient = new MockHttpClient([$mockResponse1, $mockResponse2]);
        $client = new VCRHttpClient($baseClient);

        $response1 = $client->request('GET', 'https://example.com/1');
        $response2 = $client->request('GET', 'https://example.com/2');
        $stream = $client->stream([$response1, $response2]);

        $this->assertInstanceOf(
            \Symfony\Contracts\HttpClient\ResponseStreamInterface::class,
            $stream,
            'stream() should return a ResponseStreamInterface.'
        );
    }

    public function testStreamHandlesMockResponsesDirectly(): void
    {
        $mockResponses = [
            new MockResponse('response1'),
            new MockResponse('response2'),
        ];

        $baseClient = new MockHttpClient();
        $client = new VCRHttpClient($baseClient);

        $stream = $client->stream($mockResponses);

        $this->assertInstanceOf(
            \Symfony\Contracts\HttpClient\ResponseStreamInterface::class,
            $stream,
            'stream() should handle array of MockResponse instances.'
        );

        // Verify the custom stream implementation works
        $count = 0;
        foreach ($stream as $response => $chunk) {
            $this->assertInstanceOf(MockResponse::class, $response);
            $this->assertTrue($chunk->isLast(), 'Chunk should be marked as last for MockResponse.');
            ++$count;
        }

        $this->assertEquals(2, $count, 'Stream should yield all MockResponses.');
    }

    public function testNormalizeTransportExceptionRemovesTrailingSlash(): void
    {
        $baseClient = new MockHttpClient(function (): void {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Failed to connect for "http://example.com/".');
        });

        $client = new VCRHttpClient($baseClient);

        $this->expectException(\Symfony\Component\HttpClient\Exception\TransportException::class);
        $this->expectExceptionMessage('Failed to connect for "http://example.com".');

        $response = $client->request('GET', 'http://example.com');
        // Force the request to execute (Symfony HttpClient is lazy)
        $response->getHeaders();
    }

    public function testWrappingCurlHttpClient(): void
    {
        $curlClient = new \Symfony\Component\HttpClient\CurlHttpClient();
        $client = new VCRHttpClient($curlClient);

        $this->assertInstanceOf(VCRHttpClient::class, $client);
    }

    public function testWrappingNativeHttpClient(): void
    {
        $nativeClient = new \Symfony\Component\HttpClient\NativeHttpClient();
        $client = new VCRHttpClient($nativeClient);

        $this->assertInstanceOf(VCRHttpClient::class, $client);
    }

    public function testWrappingTraceableHttpClient(): void
    {
        $baseClient = new MockHttpClient();
        $traceableClient = new \Symfony\Component\HttpClient\TraceableHttpClient($baseClient);
        $client = new VCRHttpClient($traceableClient);

        $this->assertInstanceOf(VCRHttpClient::class, $client);
    }

    public function testHandlesSymfonySpecificOptions(): void
    {
        $mockResponse = new MockResponse('test');
        $baseClient = new MockHttpClient($mockResponse);
        $client = new VCRHttpClient($baseClient);

        // Test with Symfony-specific options
        $response = $client->request('GET', 'https://example.com', [
            'max_redirects' => 5,
            'http_version' => '2.0',
            'resolve' => ['example.com' => '127.0.0.1'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testChainedWithOptionsCreatesNewInstance(): void
    {
        $baseClient = new MockHttpClient();
        $client = new VCRHttpClient($baseClient);

        $client1 = $client->withOptions(['timeout' => 10]);
        $client2 = $client1->withOptions(['timeout' => 20]);

        $this->assertNotSame($client, $client1);
        $this->assertNotSame($client1, $client2);
        $this->assertInstanceOf(VCRHttpClient::class, $client2);
    }
}
