<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Hooks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Integration\SymfonyHttpClient\Support\JsonPlaceholderClient;
use VCR\VCR;

/**
 * Tests that code transformation automatically wraps Symfony HttpClient.
 */
class CodeTransformTest extends TestCase
{
    protected function setUp(): void
    {
        VCR::configure()
            ->setCassettePath(__DIR__.'/../../../fixtures/symfony_httpclient')
            ->enableLibraryHooks(['symfony_http_client'])  // Enable code transformation hook!
            ->setMode('once');

        VCR::turnOn();
    }

    protected function tearDown(): void
    {
        VCR::eject();
        VCR::turnOff();
    }

    public function testCodeTransformWrapsCurlHttpClient(): void
    {
        VCR::insertCassette('curl_get.yml');

        // Direct instantiation - should be automatically transformed to VCRHttpClient!
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
}
