<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Basic GET + POST record/replay/passthrough via CurlHook (default Guzzle handler).
 *
 * A single Client instance is shared across record and replay phases so that
 * Guzzle reuses curl handles via curl_reset() rather than curl_init(). This is
 * important because VCR's curlReset() clears the stale $responses entry for the
 * handle ID, preventing curlMultiExec() from skipping the handle during replay.
 * Without this, PHP may reuse an integer ID (spl_object_id recycling) from the
 * previous phase and curlMultiExec() would treat the handle as already processed.
 * See issue #432 for the underlying CurlHook bug.
 *
 * Cassette names prefixed 'guzzle-basic-' to avoid VCRFactory cache collisions.
 */
final class BasicLifecycleTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $client = new Client();
        $this->recordAndReplay(
            'guzzle-basic-get.yml',
            fn (): int => $client->get(self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPassthroughGetRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new Client())->get(self::$baseUrl.'/get')->getStatusCode(),
        );
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $client = new Client();
        $this->recordAndReplay(
            'guzzle-basic-post.yml',
            fn (): int => $client->post(self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }

    public function testPassthroughPostRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new Client())->post(self::$baseUrl.'/post', ['body' => 'hello=world'])->getStatusCode(),
        );
    }
}
