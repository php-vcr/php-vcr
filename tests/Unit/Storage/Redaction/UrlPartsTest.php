<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\UrlParts;

final class UrlPartsTest extends TestCase
{
    /**
     * @dataProvider roundTrippingUrlProvider
     */
    public function testEveryComponentSurvivesAParseAndRebuildRoundTrip(string $url): void
    {
        $parts = UrlParts::parse($url);

        $this->assertNotNull($parts);
        $this->assertSame($url, UrlParts::toUrl($parts));
    }

    /**
     * @return array<string,string[]>
     */
    public static function roundTrippingUrlProvider(): array
    {
        return [
            'plain' => ['https://api.example.com/status'],
            'with port' => ['https://api.example.com:8443/status'],
            'with query' => ['https://api.example.com/search?q=cats&page=2'],
            'with fragment' => ['https://api.example.com/docs#section'],
            'with credentials' => ['https://alice:hunter2@api.example.com/private'],
            'with user only' => ['https://alice@api.example.com/private'],
            'everything' => ['https://alice:hunter2@api.example.com:8443/orders/42?user=alice#top'],
            'no path' => ['https://api.example.com'],
        ];
    }

    public function testAnEmptyQueryComponentIsDroppedRatherThanLeavingADanglingQuestionMark(): void
    {
        $this->assertSame(
            'https://api.example.com/search',
            UrlParts::toUrl(['scheme' => 'https', 'host' => 'api.example.com', 'path' => '/search', 'query' => ''])
        );
    }

    public function testRewriteQueryParameterLeavesEveryOtherParameterByteIdentical(): void
    {
        $query = UrlParts::rewriteQueryParameter(
            'client.secret=abc&token=xyz&a[]=1&a[]=2&next=%2Fhome',
            'token',
            static fn (string $value): string => strtoupper($value)
        );

        $this->assertSame('client.secret=abc&token=XYZ&a[]=1&a[]=2&next=%2Fhome', $query);
    }

    public function testRewriteQueryParameterFindsANameThatParseStrWouldMangle(): void
    {
        $query = UrlParts::rewriteQueryParameter(
            'client.secret=abc&token=xyz',
            'client.secret',
            static fn (): string => 'REDACTED'
        );

        $this->assertSame('client.secret=REDACTED&token=xyz', $query);
    }

    public function testRewriteQueryParameterRewritesEveryOccurrenceSoNoCopyOfTheSecretSurvives(): void
    {
        $query = UrlParts::rewriteQueryParameter(
            'token=xyz&q=cats&token=xyz',
            'token',
            static fn (): string => 'REDACTED'
        );

        $this->assertSame('token=REDACTED&q=cats&token=REDACTED', $query);
    }

    public function testRewriteQueryParameterComparesTheNameDecoded(): void
    {
        $query = UrlParts::rewriteQueryParameter(
            'api%20key=abc',
            'api key',
            static fn (): string => 'REDACTED'
        );

        $this->assertSame('api%20key=REDACTED', $query);
    }

    public function testRewriteQueryParameterPassesTheDecodedCurrentValueToTheReplacement(): void
    {
        $seen = null;

        UrlParts::rewriteQueryParameter('token=a%20b', 'token', static function (string $value) use (&$seen): string {
            $seen = $value;

            return 'REDACTED';
        });

        $this->assertSame('a b', $seen);
    }

    public function testRewriteQueryParameterTreatsAValuelessParameterAsAnEmptyValue(): void
    {
        $query = UrlParts::rewriteQueryParameter('flag&q=cats', 'flag', static fn (string $value): string => 'was:'.$value);

        $this->assertSame('flag=was:&q=cats', $query);
    }

    /**
     * Re-encoding the replacement — with `urlencode()`, `rawurlencode()`, or anything else — cannot
     * reproduce byte-for-byte what an arbitrary client puts on the wire, and the `query_string`
     * matcher compares the raw query string. The replacement is therefore written verbatim.
     */
    public function testRewriteQueryParameterWritesTheReplacementVerbatimWithoutEncodingIt(): void
    {
        $query = UrlParts::rewriteQueryParameter(
            'email=old&q=cats',
            'email',
            static fn (): string => 'alice@example.com two words ~a/b:c'
        );

        $this->assertSame('email=alice@example.com two words ~a/b:c&q=cats', $query);
    }

    public function testRewriteQueryParameterReturnsNullWhenTheParameterIsAbsent(): void
    {
        $this->assertNull(UrlParts::rewriteQueryParameter('q=cats', 'token', static fn (): string => 'REDACTED'));
    }

    public function testParseReturnsNullForAnUnparseableUrl(): void
    {
        $this->assertNull(UrlParts::parse('https://:8443/path'));
    }
}
