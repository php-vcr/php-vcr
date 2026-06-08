<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * PUT / DELETE / PATCH record+replay via CurlHook.
 *
 * Uses a single Client per test method so Guzzle reuses handles via curl_reset()
 * instead of curl_init(), avoiding the stale $responses issue in CurlHook (#432).
 * Cassette names prefixed 'guzzle-methods-' to avoid VCRFactory cache collisions.
 */
final class HttpMethodsTest extends AbstractHttpServerIntegrationTestCase
{
    public function testPutRequestRecordAndReplay(): void
    {
        $client = new Client();
        $this->recordAndReplay(
            'guzzle-methods-put.yml',
            fn (): int => $client->put(self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode(),
        );
    }

    public function testDeleteRequestRecordAndReplay(): void
    {
        $client = new Client();
        $this->recordAndReplay(
            'guzzle-methods-delete.yml',
            fn (): int => $client->delete(self::$baseUrl.'/delete')->getStatusCode(),
        );
    }

    public function testPatchRequestRecordAndReplay(): void
    {
        $client = new Client();
        $this->recordAndReplay(
            'guzzle-methods-patch.yml',
            fn (): int => $client->patch(self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode(),
        );
    }
}
