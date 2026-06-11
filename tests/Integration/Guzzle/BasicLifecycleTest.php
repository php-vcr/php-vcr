<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Basic GET + POST record/replay/passthrough via CurlHook (default Guzzle handler).
 *
 * Cassette names prefixed 'guzzle-basic-' to avoid VCRFactory cache collisions.
 */
final class BasicLifecycleTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-basic-get.yml',
            static fn (): int => (new Client())->get(self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPassthroughGetRequest(): void
    {
        $this->assertPassthrough(
            static fn (): int => (new Client())->get(self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-basic-post.yml',
            static fn (): int => (new Client())->post(self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }

    public function testPassthroughPostRequest(): void
    {
        $this->assertPassthrough(
            static fn (): int => (new Client())->post(self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }
}
