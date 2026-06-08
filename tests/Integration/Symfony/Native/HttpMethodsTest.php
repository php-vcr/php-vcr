<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * PUT / DELETE / PATCH record/replay/passthrough for Symfony NativeHttpClient.
 * Record/replay skipped — NativeHttpClient sends headers as array. See #329.
 * Cassette names prefixed 'symfony-native-methods-'.
 */
final class HttpMethodsTest extends AbstractHttpServerIntegrationTestCase
{
    public function testPutRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $this->recordAndReplay(
            'symfony-native-methods-put.yml',
            fn (): int => (new NativeHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode(),
        );
    }

    public function testPassthroughPutRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new NativeHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode(),
        );
    }

    public function testDeleteRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $this->recordAndReplay(
            'symfony-native-methods-delete.yml',
            fn (): int => (new NativeHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode(),
        );
    }

    public function testPassthroughDeleteRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new NativeHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode(),
        );
    }

    public function testPatchRequestRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $this->recordAndReplay(
            'symfony-native-methods-patch.yml',
            fn (): int => (new NativeHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode(),
        );
    }

    public function testPassthroughPatchRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new NativeHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode(),
        );
    }
}
