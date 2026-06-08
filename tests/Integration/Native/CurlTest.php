<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Native;

use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Verifies VCR interception of bare curl_* calls via CurlHook.
 * Cassette names prefixed 'native-curl-' to avoid VCRFactory cache collisions.
 */
final class CurlTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'native-curl-get.yml',
            fn (): int => $this->curlGet(self::$baseUrl.'/get'),
        );
    }

    public function testPassthroughGetRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => $this->curlGet(self::$baseUrl.'/get'),
        );
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $this->recordAndReplay(
            'native-curl-post.yml',
            fn (): int => $this->curlPost(self::$baseUrl.'/post', 'hello=world'),
        );
    }

    public function testPassthroughPostRequest(): void
    {
        $this->assertPassthrough(
            fn (): int => $this->curlPost(self::$baseUrl.'/post', 'hello=world'),
        );
    }

    private function curlGet(string $url): int
    {
        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $statusCode;
    }

    private function curlPost(string $url, string $body): int
    {
        $ch = curl_init($url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_POST, true);
        curl_setopt($ch, \CURLOPT_POSTFIELDS, $body);
        curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $statusCode;
    }
}
