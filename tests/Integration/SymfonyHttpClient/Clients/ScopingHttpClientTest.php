<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Clients;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use VCR\VCR;

/**
 * Tests for ScopingHttpClient compatibility with VCR.
 *
 * ScopingHttpClient is a decorator that restricts requests to specific base URIs.
 */
class ScopingHttpClientTest extends TestCase
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

    public function testScopingHttpClientWithSingleScope(): void
    {
        VCR::insertCassette('scoping_single.yml');

        $baseClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $scopedClient = new ScopingHttpClient($baseClient, [
            'https://jsonplaceholder.typicode.com' => [],
        ]);

        $response = $scopedClient->request('GET', 'https://jsonplaceholder.typicode.com/posts/1');
        $content = $response->getContent();

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertEquals(1, $data['id']);
    }

    public function testScopingHttpClientWithMultipleScopes(): void
    {
        VCR::insertCassette('scoping_multiple.yml');

        $baseClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $scopedClient = new ScopingHttpClient($baseClient, [
            'https://jsonplaceholder.typicode.com' => ['headers' => ['X-Scope' => 'api1']],
            'https://postman-echo.com' => ['headers' => ['X-Scope' => 'api2']],
        ]);

        $response1 = $scopedClient->request('GET', 'https://jsonplaceholder.typicode.com/posts/1');
        $this->assertEquals(200, $response1->getStatusCode());

        $response2 = $scopedClient->request('GET', 'https://postman-echo.com/get');
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testScopingHttpClientWithDefaultOptions(): void
    {
        VCR::insertCassette('scoping_default_options.yml');

        $baseClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        $scopedClient = new ScopingHttpClient($baseClient, [
            'https://jsonplaceholder.typicode.com' => [
                'headers' => [
                    'User-Agent' => 'VCR-Test-Client/1.0',
                ],
            ],
        ]);

        $response = $scopedClient->request('GET', 'https://jsonplaceholder.typicode.com/posts/1');
        $this->assertEquals(200, $response->getStatusCode());

        $content = $response->getContent();
        $data = json_decode($content, true);
        $this->assertIsArray($data);
    }

    public function testScopingHttpClientWithRelativeUrls(): void
    {
        VCR::insertCassette('scoping_relative.yml');

        $baseClient = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $scopedClient = new ScopingHttpClient($baseClient, [
            '.*' => ['base_uri' => 'https://jsonplaceholder.typicode.com'],
        ]);

        $response = $scopedClient->request('GET', 'https://jsonplaceholder.typicode.com/posts/1');
        $content = $response->getContent();

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertEquals(1, $data['id']);
    }
}
