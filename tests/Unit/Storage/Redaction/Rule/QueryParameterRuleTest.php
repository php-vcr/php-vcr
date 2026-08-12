<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\RequestMatcher;
use VCR\Storage\Redaction\MissingSecretException;
use VCR\Storage\Redaction\PlaceholderCollisionException;
use VCR\Storage\Redaction\Rule\QueryParameterRule;
use VCR\Storage\Redaction\Scope;

final class QueryParameterRuleTest extends TestCase
{
    /**
     * Pinned as a literal rather than read back from Placeholder: the format ends up in committed
     * cassettes, so a change to it is a change to what users see on disk and has to be deliberate.
     * It appears inside the URL exactly like this, unencoded.
     */
    private const TOKEN_PLACEHOLDER = '<<REDACTED:QUERY_PARAMETER:token>>';

    private const SECRET = 'super-secret-token';

    /**
     * @return array<string,mixed>
     */
    private function recording(string $url): array
    {
        return [
            'request' => [
                'method' => 'GET',
                'url' => $url,
                'headers' => ['Host' => 'api.example.com'],
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
            ],
            'index' => 0,
        ];
    }

    public function testRedactWritesThePlaceholderRatherThanTheSecretItself(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);

        $redacted = $rule->redact($this->recording('https://api.example.com/search?token='.self::SECRET.'&q=cats'));

        $this->assertSame(
            'https://api.example.com/search?token='.self::TOKEN_PLACEHOLDER.'&q=cats',
            $redacted['request']['url']
        );
        $this->assertStringNotContainsString(self::SECRET, $redacted['request']['url']);
    }

    public function testThePlaceholderIsDerivedFromTheParameterNameSoReRecordingIsByteIdentical(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);
        $recording = $this->recording('https://api.example.com/search?token='.self::SECRET);

        $this->assertSame($rule->redact($recording), $rule->redact($recording));
    }

    public function testRedactLeavesOtherParametersAndTheRestOfTheUrlUntouched(): void
    {
        $rule = new QueryParameterRule('signature', 'abc123');
        $original = 'https://api.example.com/orders/42?user=alice&signature=abc123&next=%2Fhome#section';

        $redacted = $rule->redact($this->recording($original));

        $url = $redacted['request']['url'];
        $this->assertStringStartsWith('https://api.example.com/orders/42?', $url);
        $this->assertStringContainsString('user=alice', $url);
        $this->assertStringContainsString('signature=<<REDACTED:QUERY_PARAMETER:signature>>', $url);
        $this->assertStringNotContainsString('abc123', $url);
        $this->assertStringContainsString('next=%2Fhome', $url);
        $this->assertStringEndsWith('#section', $url);
    }

    public function testRedactIsANoOpWhenTheParameterIsNotPresent(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);
        $original = 'https://api.example.com/search?q=cats';

        $redacted = $rule->redact($this->recording($original));

        $this->assertSame($original, $redacted['request']['url']);
    }

    public function testRedactIsANoOpWhenTheUrlHasNoQueryString(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);
        $original = 'https://api.example.com/search';

        $redacted = $rule->redact($this->recording($original));

        $this->assertSame($original, $redacted['request']['url']);
    }

    public function testRestoreWritesTheRealSecretBackIntoTheParameter(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);

        $restored = $rule->restore($rule->redact($this->recording('https://api.example.com/search?token='.self::SECRET.'&q=cats')));

        $this->assertSame(
            'https://api.example.com/search?token=super-secret-token&q=cats',
            $restored['request']['url']
        );
    }

    public function testRedactAndRestoreRoundTripBackToTheOriginalRecording(): void
    {
        $recording = $this->recording('https://api.example.com/search?token='.self::SECRET.'&q=cats');
        $rule = new QueryParameterRule('token', self::SECRET);

        $this->assertSame($recording, $rule->restore($rule->redact($recording)));
    }

    public function testRestoreIsANoOpWithoutASource(): void
    {
        $recording = $this->recording('https://api.example.com/search?token=secret&q=cats');
        $rule = new QueryParameterRule('token');

        $restored = $rule->restore($recording);

        $this->assertSame($recording, $restored);
    }

    public function testRedactBlanksTheParameterValueWithoutASource(): void
    {
        $rule = new QueryParameterRule('token');

        $redacted = $rule->redact($this->recording('https://api.example.com/search?token=secret&q=cats'));

        $this->assertSame('https://api.example.com/search?token=&q=cats', $redacted['request']['url']);
    }

    public function testRedactThrowsMissingSecretExceptionWhenSourceResolvesToEmptyString(): void
    {
        $rule = new QueryParameterRule('token', '');

        $this->expectException(MissingSecretException::class);
        $this->expectExceptionMessage(self::TOKEN_PLACEHOLDER);

        $rule->redact($this->recording('https://api.example.com/search?token=secret'));
    }

    public function testRedactThrowsPlaceholderCollisionExceptionWhenTheValueAlreadyCarriesThePlaceholder(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);
        $url = 'https://api.example.com/search?token='.self::TOKEN_PLACEHOLDER;

        $this->expectException(PlaceholderCollisionException::class);
        $this->expectExceptionMessage('request.url');

        $rule->redact($this->recording($url));
    }

    /**
     * Values are written raw but read decoded, so a placeholder that reached the URL percent-encoded
     * is still recognised rather than silently surviving restore() as the caller's own text.
     */
    public function testRedactThrowsPlaceholderCollisionExceptionForAPercentEncodedPlaceholderToo(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);
        $url = 'https://api.example.com/search?token='.rawurlencode(self::TOKEN_PLACEHOLDER);

        $this->expectException(PlaceholderCollisionException::class);
        $this->expectExceptionMessage('request.url');

        $rule->redact($this->recording($url));
    }

    /**
     * The secret must be gone from what reaches the disk, and back in place by the time the real
     * `query_string` matcher compares the recording against a live request.
     */
    public function testTheRedactRestoreRoundTripSatisfiesTheRealQueryStringMatcherAgainstALiveRequest(): void
    {
        $recording = $this->recording('https://api.example.com/search?token='.self::SECRET.'&q=cats');
        $rule = new QueryParameterRule('token', self::SECRET);

        $onDisk = $rule->redact($recording);
        $this->assertStringNotContainsString(self::SECRET, (string) json_encode($onDisk['request']));

        $liveRequest = Request::fromArray($recording['request']);
        $this->assertFalse(
            RequestMatcher::matchQueryString(Request::fromArray($onDisk['request']), $liveRequest),
            'Sanity check: without restore() the recorded request cannot match, or nothing was hidden.'
        );

        $this->assertTrue(
            RequestMatcher::matchQueryString(Request::fromArray($rule->restore($onDisk)['request']), $liveRequest)
        );
    }

    /**
     * Re-encoding the restored value used to break exactly these: `urlencode()` escapes an `@` and a
     * `:`, `/` and `~`, and writes a space as `+`, none of which a client that sent the character
     * literally will reproduce — and the `query_string` matcher compares the raw query string
     * byte-for-byte. The value now goes back in exactly as it came out.
     *
     * @dataProvider awkwardSecretProvider
     */
    public function testARestoredValueMatchesTheRealQueryStringMatcherWhateverCharactersItCarries(string $secret): void
    {
        $recording = $this->recording('https://api.example.com/search?email='.$secret.'&q=1');
        $rule = new QueryParameterRule('email', $secret);

        $onDisk = $rule->redact($recording);
        $this->assertStringNotContainsString($secret, $onDisk['request']['url']);

        $liveRequest = Request::fromArray($recording['request']);
        $replayedRequest = Request::fromArray($rule->restore($onDisk)['request']);

        $this->assertSame($liveRequest->getQuery(), $replayedRequest->getQuery());
        $this->assertTrue(RequestMatcher::matchQueryString($replayedRequest, $liveRequest));
    }

    /**
     * @return array<string,string[]>
     */
    public static function awkwardSecretProvider(): array
    {
        return [
            'at sign' => ['alice@example.com'],
            'colon' => ['scheme:value'],
            'slash' => ['a/b'],
            'tilde' => ['~alice'],
            'literal space' => ['two words'],
            'already percent-encoded' => ['two%20words'],
        ];
    }

    /**
     * Round-tripping the query string through parse_str()/http_build_query() used to rewrite
     * parameters this rule never targeted — `client.secret` came back as `client_secret` — which
     * fails the query_string matcher on replay even though the rule reports invalidating nothing.
     */
    public function testRedactLeavesAParameterWhoseNameParseStrWouldMangleByteIdentical(): void
    {
        $recording = $this->recording('https://api.example.com/s?client.secret=abc&token='.self::SECRET.'&a[]=1');
        $rule = new QueryParameterRule('token', self::SECRET);

        $redacted = $rule->redact($recording);

        $this->assertStringContainsString('client.secret=abc', $redacted['request']['url']);
        $this->assertStringContainsString('a[]=1', $redacted['request']['url']);
        $this->assertSame($recording, $rule->restore($redacted));
    }

    /**
     * Same root cause seen from the other side: such a name could never be found, so its value
     * stayed on the cassette in plaintext with no error to show for it.
     */
    public function testRedactFindsAParameterWhoseOwnNameParseStrWouldMangle(): void
    {
        $recording = $this->recording('https://api.example.com/s?client.secret='.self::SECRET);
        $rule = new QueryParameterRule('client.secret', self::SECRET);

        $redacted = $rule->redact($recording);

        $this->assertStringNotContainsString(self::SECRET, $redacted['request']['url']);
        $this->assertSame($recording, $rule->restore($redacted));
    }

    public function testRedactRewritesEveryOccurrenceOfARepeatedParameter(): void
    {
        $recording = $this->recording('https://api.example.com/s?token='.self::SECRET.'&q=cats&token='.self::SECRET);
        $rule = new QueryParameterRule('token', self::SECRET);

        $redacted = $rule->redact($recording);

        $this->assertStringNotContainsString(self::SECRET, $redacted['request']['url']);
        $this->assertSame(2, substr_count($redacted['request']['url'], self::TOKEN_PLACEHOLDER));
    }

    public function testScopeIsAlwaysRequest(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);

        $this->assertSame(Scope::REQUEST, $rule->scope());
    }

    public function testIsReversibleIsTrueWhenASourceIsGiven(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);

        $this->assertTrue($rule->isReversible());
    }

    public function testIsReversibleIsFalseWithoutASource(): void
    {
        $rule = new QueryParameterRule('token');

        $this->assertFalse($rule->isReversible());
    }

    public function testAffectedMatchersIsEmptyWhenReversible(): void
    {
        $rule = new QueryParameterRule('token', self::SECRET);

        $this->assertSame([], $rule->affectedMatchers());
    }

    public function testAffectedMatchersIsQueryStringWhenIrreversible(): void
    {
        $rule = new QueryParameterRule('token');

        $this->assertSame(['query_string'], $rule->affectedMatchers());
    }
}
