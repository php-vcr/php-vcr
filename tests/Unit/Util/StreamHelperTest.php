<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\Util\StreamHelper;

final class StreamHelperTest extends TestCase
{
    /** @return array<string, mixed> */
    public static function streamContexts(): array
    {
        return [
            'header' => [
                ['header' => 'Content-Type: application/json'],
                static function (Request $request): void {
                    Assert::assertEquals('application/json', $request->getHeader('Content-Type'));
                },
            ],

            'header with trailing newline' => [
                ['header' => "Content-Type: application/json\r\n"],
                static function (Request $request): void {
                    Assert::assertEquals('application/json', $request->getHeader('Content-Type'));
                },
            ],

            'multiple headers' => [
                ['header' => "Content-Type: application/json\r\nContent-Length: 123"],
                static function (Request $request): void {
                    Assert::assertEquals('application/json', $request->getHeader('Content-Type'));
                    Assert::assertEquals('123', $request->getHeader('Content-Length'));
                },
            ],

            'header as array' => [
                ['header' => ['Content-Type: application/json']],
                static function (Request $request): void {
                    Assert::assertEquals('application/json', $request->getHeader('Content-Type'));
                },
            ],

            'multiple headers as array' => [
                ['header' => ['Content-Type: application/json', 'Content-Length: 123']],
                static function (Request $request): void {
                    Assert::assertEquals('application/json', $request->getHeader('Content-Type'));
                    Assert::assertEquals('123', $request->getHeader('Content-Length'));
                },
            ],

            'user_agent' => [
                ['user_agent' => 'example'],
                static function (Request $request): void {
                    Assert::assertEquals('example', $request->getHeader('User-Agent'));
                },
            ],

            'content' => [
                ['content' => 'example'],
                static function (Request $request): void {
                    Assert::assertEquals('example', $request->getBody());
                },
            ],

            'follow_location' => [
                ['follow_location' => '0'],
                static function (Request $request): void {
                    Assert::assertEquals(false, $request->getCurlOption(\CURLOPT_FOLLOWLOCATION));
                },
            ],

            'max_redirects' => [
                ['max_redirects' => '2'],
                static function (Request $request): void {
                    Assert::assertEquals('2', $request->getCurlOption(\CURLOPT_MAXREDIRS));
                },
            ],

            'timeout' => [
                ['timeout' => '100'],
                static function (Request $request): void {
                    Assert::assertEquals('100', $request->getCurlOption(\CURLOPT_TIMEOUT));
                },
            ],
        ];
    }

    /**
     * @dataProvider streamContexts
     *
     * @param array<mixed> $context
     * @param callable     $testCallback
     */
    public function testStreamHttpContext(array $context, $testCallback): void
    {
        $context = stream_context_create([
            'http' => $context,
        ]);

        $request = StreamHelper::createRequestFromStreamContext($context, 'http://example.com');
        $testCallback($request);
    }

    /**
     * @dataProvider resolveUrlProvider
     */
    public function testResolveUrl(string $base, string $location, string $expected): void
    {
        $this->assertSame($expected, StreamHelper::resolveUrl($base, $location));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function resolveUrlProvider(): array
    {
        return [
            'absolute' => ['http://example.com/a', 'https://other.com/b', 'https://other.com/b'],
            'scheme relative' => ['https://example.com/a', '//cdn.example.com/b', 'https://cdn.example.com/b'],
            'absolute path' => ['http://example.com/a/b', '/c/d', 'http://example.com/c/d'],
            'relative path' => ['http://example.com/a/b', 'c', 'http://example.com/a/c'],
            'relative root' => ['http://example.com', 'c', 'http://example.com/c'],
            'with port' => ['http://example.com:8080/a/b', '/c', 'http://example.com:8080/c'],
        ];
    }

    public function testShouldFollowLocationDefaultsToTrue(): void
    {
        $this->assertTrue(StreamHelper::shouldFollowLocation(null));
    }

    public function testShouldFollowLocationHonorsContextDisable(): void
    {
        $context = stream_context_create(['http' => ['follow_location' => 0]]);
        $this->assertFalse(StreamHelper::shouldFollowLocation($context));
    }

    public function testMaxRedirectsDefaultsTo20(): void
    {
        $this->assertSame(20, StreamHelper::maxRedirects(null));
    }

    public function testMaxRedirectsHonorsContext(): void
    {
        $context = stream_context_create(['http' => ['max_redirects' => 5]]);
        $this->assertSame(5, StreamHelper::maxRedirects($context));
    }
}
