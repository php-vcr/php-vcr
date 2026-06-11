<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Basic GET + POST record/replay/passthrough for Symfony NativeHttpClient via StreamWrapperHook.
 *
 * Passthrough tests (no VCR) verify that NativeHttpClient can reach the test server.
 * Cassette names prefixed 'symfony-native-basic-' to avoid VCRFactory cache collisions.
 */
final class BasicLifecycleTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-native-basic-get.yml',
            static fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPassthroughGetRequest(): void
    {
        $this->assertPassthrough(
            static fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-native-basic-post.yml',
            static fn (): int => (new NativeHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }

    public function testPassthroughPostRequest(): void
    {
        $this->assertPassthrough(
            static fn (): int => (new NativeHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }
}
