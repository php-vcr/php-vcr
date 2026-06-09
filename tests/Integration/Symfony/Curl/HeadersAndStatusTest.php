<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Custom headers and non-200 status codes for Symfony CurlHttpClient.
 * Cassette names prefixed 'symfony-curl-headers-'.
 */
final class HeadersAndStatusTest extends AbstractHttpServerIntegrationTestCase
{
    public function testCustomRequestHeadersRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-headers-custom.yml',
            fn (): int => (new CurlHttpClient())->request('GET', self::$baseUrl.'/get', [
                'headers' => ['X-Custom-Header' => 'test-value'],
            ])->getStatusCode(),
        );
    }

    public function testStatus404RecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-headers-404.yml',
            fn (): int => (new CurlHttpClient())->request('GET', self::$baseUrl.'/status/404')->getStatusCode(),
            404,
        );
    }

    public function testStatus500RecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-headers-500.yml',
            fn (): int => (new CurlHttpClient())->request('GET', self::$baseUrl.'/status/500')->getStatusCode(),
            500,
        );
    }
}
