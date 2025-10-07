<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Features;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\VCR;

/**
 * Tests for HTTP authentication with Symfony HttpClient and VCR.
 *
 * Tests Basic Auth, Bearer tokens, and auth options.
 */
class AuthenticationTest extends TestCase
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

    public function testBasicAuthWithCurlHttpClient(): void
    {
        VCR::insertCassette('auth_basic_curl.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'auth_basic' => ['user', 'passwd'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBasicAuthWithNativeHttpClient(): void
    {
        VCR::insertCassette('auth_basic_native.yml');

        $client = new NativeHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'auth_basic' => ['user', 'passwd'],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBasicAuthWithManualHeader(): void
    {
        VCR::insertCassette('auth_manual_header.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode('user:passwd'),
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBearerTokenAuth(): void
    {
        VCR::insertCassette('auth_bearer.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'auth_bearer' => 'test-token-123',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testBearerTokenWithManualHeader(): void
    {
        VCR::insertCassette('auth_bearer_manual.yml');

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        $response = $client->request('GET', 'https://jsonplaceholder.typicode.com/posts/1', [
            'headers' => [
                'Authorization' => 'Bearer test-token-456',
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
