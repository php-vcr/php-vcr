<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Custom headers and non-200 status codes for Symfony NativeHttpClient.
 * Record/replay skipped — NativeHttpClient sends headers as array. See #329.
 * Cassette names prefixed 'symfony-native-headers-'.
 */
final class HeadersAndStatusTest extends AbstractHttpServerIntegrationTestCase
{
    public function testCustomRequestHeadersRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $this->recordAndReplay(
            'symfony-native-headers-custom.yml',
            fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/get', [
                'headers' => ['X-Custom-Header' => 'test-value'],
            ])->getStatusCode(),
        );
    }

    public function testStatus404RecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $this->recordAndReplay(
            'symfony-native-headers-404.yml',
            fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/status/404')->getStatusCode(),
            404,
        );
    }

    public function testStatus500RecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $this->recordAndReplay(
            'symfony-native-headers-500.yml',
            fn (): int => (new NativeHttpClient())->request('GET', self::$baseUrl.'/status/500')->getStatusCode(),
            500,
        );
    }
}
