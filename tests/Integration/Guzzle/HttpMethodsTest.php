<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * PUT / DELETE / PATCH record+replay via CurlHook.
 *
 * Cassette names prefixed 'guzzle-methods-' to avoid VCRFactory cache collisions.
 */
final class HttpMethodsTest extends AbstractHttpServerIntegrationTestCase
{
    public function testPutRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-methods-put.yml',
            fn (): int => (new Client())->put(self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode(),
        );
    }

    public function testDeleteRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-methods-delete.yml',
            fn (): int => (new Client())->delete(self::$baseUrl.'/delete')->getStatusCode(),
        );
    }

    public function testPatchRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-methods-patch.yml',
            fn (): int => (new Client())->patch(self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode(),
        );
    }
}
