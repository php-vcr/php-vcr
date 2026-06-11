<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Basic GET + POST record/replay/passthrough for Symfony CurlHttpClient via CurlHook.
 *
 * Cassette names prefixed 'symfony-curl-basic-' to avoid VCRFactory cache collisions.
 */
final class BasicLifecycleTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-basic-get.yml',
            static fn (): int => (new CurlHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPassthroughGetRequest(): void
    {
        $this->assertPassthrough(
            static fn (): int => (new CurlHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-basic-post.yml',
            static fn (): int => (new CurlHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }

    public function testPassthroughPostRequest(): void
    {
        $this->assertPassthrough(
            static fn (): int => (new CurlHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }
}
