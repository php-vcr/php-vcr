<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Basic GET + POST record/replay/passthrough for Symfony CurlHttpClient via CurlHook.
 *
 * Record/replay tests are skipped: VCR's CurlHook does not yet handle Symfony's
 * curl_getinfo() call that occurs before curl_multi_exec completes (issue #329,
 * PRs #415, #426). Remove the markTestSkipped() calls once that is fixed.
 *
 * Passthrough tests (no VCR) verify the library itself works correctly.
 * Cassette names prefixed 'symfony-curl-basic-' to avoid VCRFactory cache collisions.
 */
final class BasicLifecycleTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() called before curl_multi_exec completes. See #329.');

        $this->recordAndReplay(
            'symfony-curl-basic-get.yml',
            fn (): int => (new CurlHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPassthroughGetRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new CurlHttpClient())->request('GET', self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() called before curl_multi_exec completes. See #329.');

        $this->recordAndReplay(
            'symfony-curl-basic-post.yml',
            fn (): int => (new CurlHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }

    public function testPassthroughPostRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new CurlHttpClient())->request('POST', self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }
}
