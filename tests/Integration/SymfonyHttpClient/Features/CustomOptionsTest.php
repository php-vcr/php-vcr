<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Features;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\VCR;

/**
 * Tests for custom Symfony HttpClient options with VCR.
 *
 * Tests: timeout, max_redirects, headers, user_data, etc.
 */
class CustomOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        VCR::configure()
            ->setCassettePath(__DIR__.'/../../../fixtures/symfony_httpclient')
            ->enableLibraryHooks(['symfony_http_client'])
            ->setMode('new_episodes');

        VCR::turnOn();
    }

    protected function tearDown(): void
    {
        VCR::eject();
        VCR::turnOff();
    }

    public function testCustomHeaders(): void
    {
        VCR::insertCassette('options_custom_headers.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'headers' => [
                'X-Custom-Header' => 'custom-value',
                'User-Agent' => 'VCR-Test/1.0',
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertEquals(1, $data['id']);
    }

    public function testMaxRedirects(): void
    {
        VCR::insertCassette('options_max_redirects.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'max_redirects' => 5,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTimeoutOption(): void
    {
        VCR::insertCassette('options_timeout.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'timeout' => 30.0,
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }

    public function testUserDataOption(): void
    {
        VCR::insertCassette('options_user_data.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'user_data' => ['test_id' => 123, 'test_name' => 'VCR'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $info = $response->getInfo();
        $this->assertEquals(['test_id' => 123, 'test_name' => 'VCR'], $info['user_data']);
    }

    public function testQueryParameters(): void
    {
        VCR::insertCassette('options_query_params.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://postman-echo.com/get', [
            'query' => [
                'foo' => 'bar',
                'number' => 42,
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertArrayHasKey('args', $data);
        $this->assertEquals('bar', $data['args']['foo'] ?? '');
        $this->assertEquals('42', $data['args']['number'] ?? '');
    }

    public function testJsonOption(): void
    {
        VCR::insertCassette('options_json.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('POST', 'https://jsonplaceholder.typicode.com/posts', [
            'json' => [
                'title' => 'Test Post',
                'body' => 'Test Content',
                'userId' => 1,
            ],
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Test Post', $data['title'] ?? '');
    }

    public function testWithOptionsCreatesNewInstance(): void
    {
        VCR::insertCassette('options_with_options.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $clientWithOptions = $client->withOptions([
            'headers' => [
                'X-Test' => 'value',
            ],
        ]);

        $this->assertNotSame($client, $clientWithOptions);

        $response = $clientWithOptions->request('GET', 'https://jsonplaceholder.typicode.com/posts/1');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testNativeHttpClientWithCustomOptions(): void
    {
        VCR::insertCassette('options_native_custom.yml');

        $client = new NativeHttpClient([
            'verify_peer' => false,
            'verify_host' => false,
            'timeout' => 30,
        ]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }

    public function testMultipleOptionsInSingleRequest(): void
    {
        VCR::insertCassette('options_multiple.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('POST', 'https://jsonplaceholder.typicode.com/posts', [
            'headers' => [
                'X-Custom' => 'test',
            ],
            'json' => [
                'title' => 'test',
                'body' => 'test body',
                'userId' => 1,
            ],
            'timeout' => 30,
            'max_redirects' => 5,
            'user_data' => ['id' => 1],
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
    }
}
