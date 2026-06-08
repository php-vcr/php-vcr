<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Basic GET + POST record/replay/passthrough for Symfony NativeHttpClient via StreamWrapperHook.
 *
 * Record/replay tests are skipped: NativeHttpClient passes HTTP headers as an array in
 * the PHP stream context, but VCR's StreamHelper::createRequestFromStreamContext() calls
 * HttpUtil::parseRawHeader() which requires a string. Remove the markTestSkipped() calls
 * once that is fixed.
 *
 * Passthrough tests (no VCR) verify that NativeHttpClient can reach the test server.
 * Cassette names prefixed 'symfony-native-basic-' to avoid VCRFactory cache collisions.
 */
final class BasicLifecycleTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context; parseRawHeader() requires string. See #329.');

        $this->recordAndReplay(
            'symfony-native-basic-get.yml',
            fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPassthroughGetRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context; parseRawHeader() requires string. See #329.');

        $this->recordAndReplay(
            'symfony-native-basic-post.yml',
            fn (): int => (new NativeHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }

    public function testPassthroughPostRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new NativeHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }
}
