<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Hooks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CurlHttpClient;

/**
 * Test if Symfony CurlHttpClient works with ONLY the cURL hooks (no code transformation).
 *
 * This validates whether VCRHttpClient is actually needed or if standard cURL interception
 * is sufficient for Symfony HttpClient support.
 */
class CurlHookOnlyTest extends TestCase
{
    public const TEST_URL = 'https://jsonplaceholder.typicode.com/posts/1';

    protected function setUp(): void
    {
        \VCR\VCR::configure()
            ->setCassettePath(__DIR__.'/../../../fixtures/symfony_httpclient')
            ->enableLibraryHooks(['curl'])
            ->setMode('once');
    }

    protected function tearDown(): void
    {
        \VCR\VCR::eject();
        \VCR\VCR::turnOff();
    }

    public function testCurlHttpClientWithCurlHooksOnly(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('curl_hook_only_test.yml');

        $client = new CurlHttpClient([
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $response = $client->request('GET', self::TEST_URL);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(1, $data['id']);
    }

    public function testCurlHttpClientPostWithCurlHooksOnly(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('curl_hook_only_post_test.yml');

        $client = new CurlHttpClient([
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $response = $client->request('POST', 'https://jsonplaceholder.typicode.com/posts', [
            'json' => [
                'title' => 'Test Post',
                'body' => 'Test Body',
                'userId' => 1,
            ],
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Test Post', $data['title']);
    }
}
