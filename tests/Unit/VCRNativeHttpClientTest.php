<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VCR\VCRNativeHttpClient;

final class VCRNativeHttpClientTest extends TestCase
{
    public function testImplementsHttpClientInterface(): void
    {
        $client = new VCRNativeHttpClient();

        $this->assertInstanceOf(
            \Symfony\Contracts\HttpClient\HttpClientInterface::class,
            $client,
            'VCRNativeHttpClient should implement HttpClientInterface.'
        );
    }

    public function testConstructorWithDefaultOptions(): void
    {
        $client = new VCRNativeHttpClient();

        $this->assertInstanceOf(VCRNativeHttpClient::class, $client);
    }

    public function testConstructorWithOptions(): void
    {
        $client = new VCRNativeHttpClient(['timeout' => 30]);

        $this->assertInstanceOf(VCRNativeHttpClient::class, $client);
    }

    public function testConstructorWithMaxHostConnections(): void
    {
        $client = new VCRNativeHttpClient([], 10);

        $this->assertInstanceOf(VCRNativeHttpClient::class, $client);
    }

    public function testConstructorWithMaxPendingPushes(): void
    {
        $client = new VCRNativeHttpClient([], 6, 100);

        $this->assertInstanceOf(VCRNativeHttpClient::class, $client);
    }

    public function testWithOptionsReturnsNewInstance(): void
    {
        $client = new VCRNativeHttpClient();
        $newClient = $client->withOptions(['timeout' => 30]);

        $this->assertNotSame($client, $newClient, 'withOptions() should return a new instance.');
        $this->assertInstanceOf(VCRNativeHttpClient::class, $newClient);
    }

    /**
     * @group uses_internet
     */
    public function testRequestMakesRealRequest(): void
    {
        $client = new VCRNativeHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $response = $client->request('GET', 'http://example.com');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Example Domain', $response->getContent());
    }

    /**
     * @group uses_internet
     */
    public function testStreamReturnsResponseStream(): void
    {
        $client = new VCRNativeHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $response = $client->request('GET', 'http://example.com');
        $stream = $client->stream([$response]);

        $this->assertInstanceOf(
            \Symfony\Contracts\HttpClient\ResponseStreamInterface::class,
            $stream,
            'stream() should return a ResponseStreamInterface.'
        );
    }
}
