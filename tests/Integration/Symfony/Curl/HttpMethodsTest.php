<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * PUT / DELETE / PATCH record/replay/passthrough for Symfony CurlHttpClient.
 * Cassette names prefixed 'symfony-curl-methods-'.
 */
final class HttpMethodsTest extends AbstractHttpServerIntegrationTestCase
{
    public function testPutRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-methods-put.yml',
            fn (): int => (new CurlHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode(),
        );
    }

    public function testPassthroughPutRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new CurlHttpClient())->request('PUT', self::$baseUrl.'/put', ['body' => 'data=1'])->getStatusCode(),
        );
    }

    public function testDeleteRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-methods-delete.yml',
            fn (): int => (new CurlHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode(),
        );
    }

    public function testPassthroughDeleteRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new CurlHttpClient())->request('DELETE', self::$baseUrl.'/delete')->getStatusCode(),
        );
    }

    public function testPatchRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'symfony-curl-methods-patch.yml',
            fn (): int => (new CurlHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode(),
        );
    }

    public function testPassthroughPatchRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => (new CurlHttpClient())->request('PATCH', self::$baseUrl.'/patch', ['body' => 'field=value'])->getStatusCode(),
        );
    }
}
