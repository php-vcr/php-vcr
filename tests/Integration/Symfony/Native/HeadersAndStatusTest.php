<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Custom headers and non-200 status codes for Symfony NativeHttpClient.
 * Cassette names prefixed 'symfony-native-headers-'.
 */
final class HeadersAndStatusTest extends AbstractHttpServerIntegrationTestCase
{
    public function testCustomRequestHeadersRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-native-headers-custom.yml',
            static fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/get', [
                'headers' => ['X-Custom-Header' => 'test-value'],
            ])->getStatusCode(),
        );
    }

    public function testStatus404RecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-native-headers-404.yml',
            static fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/status/404')->getStatusCode(),
            404,
        );
    }

    public function testStatus500RecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-native-headers-500.yml',
            static fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/status/500')->getStatusCode(),
            500,
        );
    }
}
