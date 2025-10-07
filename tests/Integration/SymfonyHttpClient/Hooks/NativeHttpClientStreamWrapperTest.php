<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Hooks;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\NativeHttpClient;

/**
 * Test if NativeHttpClient works with ONLY stream_wrapper hooks (no StreamMetaDataProxy).
 *
 * This validates whether StreamMetaDataProxy is actually needed or if the StreamWrapperHook
 * already provides the correct format for stream_get_meta_data().
 */
class NativeHttpClientStreamWrapperTest extends TestCase
{
    public const TEST_URL = 'https://jsonplaceholder.typicode.com/posts/1';

    protected function setUp(): void
    {
        \VCR\VCR::configure()
            ->setCassettePath(__DIR__.'/../../../fixtures/symfony_httpclient')
            ->enableLibraryHooks(['stream_wrapper'])
            ->setMode('once');
    }

    protected function tearDown(): void
    {
        \VCR\VCR::eject();
        \VCR\VCR::turnOff();
    }

    public function testNativeHttpClientWithStreamWrapperOnly(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native_stream_wrapper_test.yml');

        $client = new NativeHttpClient([
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $response = $client->request('GET', self::TEST_URL);
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals(1, $data['id']);
    }
}
