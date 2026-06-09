<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Custom request headers and non-200 status codes record+replay via CurlHook.
 *
 * Cassette names prefixed 'guzzle-headers-' to avoid VCRFactory cache collisions.
 */
final class HeadersAndStatusTest extends AbstractHttpServerIntegrationTestCase
{
    public function testCustomRequestHeadersRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-headers-custom.yml',
            fn (): int => (new Client())->get(self::$baseUrl.'/get', ['headers' => ['X-Custom-Header' => 'test-value']])->getStatusCode(),
        );
    }

    public function testStatus404RecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-headers-404.yml',
            fn (): int => (new Client(['http_errors' => false]))->get(self::$baseUrl.'/status/404')->getStatusCode(),
            404,
        );
    }

    public function testStatus500RecordAndReplay(): void
    {
        $this->recordAndReplay(
            'guzzle-headers-500.yml',
            fn (): int => (new Client(['http_errors' => false]))->get(self::$baseUrl.'/status/500')->getStatusCode(),
            500,
        );
    }
}
