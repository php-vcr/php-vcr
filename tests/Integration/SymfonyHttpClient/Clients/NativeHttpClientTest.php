<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Clients;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\NativeHttpClient;

/**
 * Tests for NativeHttpClient with VCR.
 *
 * NativeHttpClient is now compatible with VCR thanks to the stream_get_meta_data() proxy.
 * The proxy function in StreamMetaDataProxy.php intercepts calls in the Symfony namespace
 * and transforms the StreamWrapperHook object into an array.
 */
class NativeHttpClientTest extends TestCase
{
    public const TEST_GET_URL = 'https://postman-echo.com/get';

    protected function setUp(): void
    {
        \VCR\VCR::configure()->setCassettePath(__DIR__.'/../../../fixtures/httpclient')
            ->enableLibraryHooks(['symfony_http_client'])

        ;
    }

    /**
     * Test that NativeHttpClient works with VCR thanks to the stream_get_meta_data() proxy.
     *
     * The proxy function in StreamMetaDataProxy.php intercepts calls to stream_get_meta_data()
     * in the Symfony\Component\HttpClient\Response namespace and transforms the StreamWrapperHook
     * object into an array that Symfony expects.
     */
    public function testNativeHttpClientWorksWithVCR(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-http-client.yml');

        $client = new NativeHttpClient();
        $response = $client->request('GET', self::TEST_GET_URL);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data, 'Response is not an array.');
        $this->assertArrayHasKey('url', $data, 'API did not return expected data.');

        \VCR\VCR::turnOff();
    }

    /**
     * This test verifies the documented workaround using VCRNativeHttpClient.
     */
    public function testWorkaroundUsingVCRNativeHttpClient(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-http-client-workaround.yml');

        $client = new \VCR\VCRNativeHttpClient();
        $response = $client->request('GET', self::TEST_GET_URL);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data, 'Response is not an array.');
        $this->assertArrayHasKey('url', $data, 'API did not return any value.');

        \VCR\VCR::turnOff();
    }
}
