<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\RequestMatcher;
use VCR\Storage\Redaction\InvalidRedactionRuleException;
use VCR\Storage\Redaction\MissingSecretException;
use VCR\Storage\Redaction\PlaceholderCollisionException;
use VCR\Storage\Redaction\Rule\HeaderRule;
use VCR\Storage\Redaction\Scope;

final class HeaderRuleTest extends TestCase
{
    /**
     * Pinned as a literal rather than read back from Placeholder: the format ends up in committed
     * cassettes, so a change to it is a change to what users see on disk and has to be deliberate.
     */
    private const AUTHORIZATION_PLACEHOLDER = '<<REDACTED:HEADER:authorization>>';

    private const SECRET = 'Bearer super-secret-token';

    /**
     * @return array<string,mixed>
     */
    private function recording(): array
    {
        return [
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.example.com/login',
                'headers' => [
                    'Authorization' => self::SECRET,
                    'X-Trace-Id' => 'abc-123',
                ],
                'body' => '{}',
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
                'headers' => [
                    'Set-Cookie' => 'session=secret-session',
                    'Content-Type' => 'application/json',
                ],
                'body' => 'welcome',
            ],
            'index' => 0,
        ];
    }

    public function testRedactWritesThePlaceholderRatherThanTheSecretItself(): void
    {
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $redacted = $rule->redact($this->recording());

        $this->assertSame(self::AUTHORIZATION_PLACEHOLDER, $redacted['request']['headers']['Authorization']);
    }

    public function testThePlaceholderIsDerivedFromTheHeaderNameSoReRecordingIsByteIdentical(): void
    {
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $first = $rule->redact($this->recording());
        $second = $rule->redact($this->recording());

        $this->assertSame($first, $second);
        $this->assertSame(
            self::AUTHORIZATION_PLACEHOLDER,
            (new HeaderRule('Authorization', 'a different secret', Scope::REQUEST))->redact($this->recording())['request']['headers']['Authorization'],
            'The placeholder must depend on the header name only, never on the secret.'
        );
    }

    public function testRedactMatchesTheHeaderNameCaseInsensitively(): void
    {
        $rule = new HeaderRule('AUTHORIZATION', self::SECRET, Scope::REQUEST);

        $redacted = $rule->redact($this->recording());

        $this->assertSame(self::AUTHORIZATION_PLACEHOLDER, $redacted['request']['headers']['Authorization']);
    }

    public function testRedactWithBothScopeAffectsRequestAndResponseHeadersOfTheSameName(): void
    {
        $recording = $this->recording();
        $recording['response']['headers']['Authorization'] = 'Bearer other-secret';
        $rule = new HeaderRule('Authorization', self::SECRET);

        $redacted = $rule->redact($recording);

        $this->assertSame(self::AUTHORIZATION_PLACEHOLDER, $redacted['request']['headers']['Authorization']);
        $this->assertSame(self::AUTHORIZATION_PLACEHOLDER, $redacted['response']['headers']['Authorization']);
    }

    public function testRestoreWritesTheRealSecretBackIntoTheRequestHeader(): void
    {
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $restored = $rule->restore($rule->redact($this->recording()));

        $this->assertSame(self::SECRET, $restored['request']['headers']['Authorization']);
    }

    public function testRedactAndRestoreRoundTripBackToTheOriginalRecording(): void
    {
        $recording = $this->recording();
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $this->assertSame($recording, $rule->restore($rule->redact($recording)));
    }

    public function testRestoreWritesTheSecretBackOnEverySideTheScopeCovers(): void
    {
        $recording = $this->recording();
        $recording['response']['headers']['Authorization'] = self::SECRET;
        $rule = new HeaderRule('Authorization', self::SECRET);

        $restored = $rule->restore($rule->redact($recording));

        $this->assertSame($recording, $restored);
    }

    public function testAResponseScopedHeaderIsRestoredToo(): void
    {
        $recording = $this->recording();
        $rule = new HeaderRule('Set-Cookie', 'session=secret-session', Scope::RESPONSE);

        $redacted = $rule->redact($recording);
        $this->assertSame('<<REDACTED:HEADER:set-cookie>>', $redacted['response']['headers']['Set-Cookie']);

        $this->assertSame($recording, $rule->restore($redacted));
    }

    public function testRestoreIsANoOpWithoutASource(): void
    {
        $recording = $this->recording();
        $rule = new HeaderRule('Authorization', null, Scope::REQUEST);

        $restored = $rule->restore($recording);

        $this->assertSame($recording, $restored);
    }

    public function testRestoreIsANoOpWhenTheHeaderIsNotInTheRecording(): void
    {
        $recording = $this->recording();
        $rule = new HeaderRule('X-Absent', self::SECRET, Scope::REQUEST);

        $this->assertSame($recording, $rule->restore($recording));
    }

    public function testRedactBlanksTheHeaderValueWithoutASource(): void
    {
        $rule = new HeaderRule('Set-Cookie', null, Scope::RESPONSE);

        $redacted = $rule->redact($this->recording());

        $this->assertSame('', $redacted['response']['headers']['Set-Cookie']);
    }

    public function testRedactThrowsMissingSecretExceptionWhenSourceResolvesToEmptyString(): void
    {
        $rule = new HeaderRule('Authorization', '', Scope::REQUEST);

        $this->expectException(MissingSecretException::class);

        $rule->redact($this->recording());
    }

    public function testRedactThrowsMissingSecretExceptionRatherThanWritingAnUnrestorableCassette(): void
    {
        $rule = new HeaderRule('Authorization', static fn (): ?string => null, Scope::REQUEST);

        $this->expectException(MissingSecretException::class);
        $this->expectExceptionMessage(self::AUTHORIZATION_PLACEHOLDER);

        $rule->redact($this->recording());
    }

    public function testRedactThrowsPlaceholderCollisionExceptionWhenTheValueAlreadyCarriesThePlaceholder(): void
    {
        $recording = $this->recording();
        $recording['request']['headers']['Authorization'] = self::AUTHORIZATION_PLACEHOLDER;
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $this->expectException(PlaceholderCollisionException::class);
        $this->expectExceptionMessage('request.headers.Authorization');

        $rule->redact($recording);
    }

    /**
     * The assertion that actually matters: what reaches the disk must not carry the secret, and
     * what `RedactingStorage::current()` hands to `Cassette::playback()` must still satisfy the
     * real `headers` matcher against a live request carrying the real secret. Asserting
     * `affectedMatchers() === []` alone proved nothing — it is the rule's own claim about itself.
     */
    public function testTheRedactRestoreRoundTripSatisfiesTheRealHeadersMatcherAgainstALiveRequest(): void
    {
        $recording = $this->recording();
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $onDisk = $rule->redact($recording);
        $this->assertStringNotContainsString(
            'super-secret-token',
            (string) json_encode($onDisk['request']),
            'The recorded request must not carry the secret.'
        );

        $liveRequest = Request::fromArray($recording['request']);
        $recordedRequest = Request::fromArray($onDisk['request']);
        $this->assertFalse(
            RequestMatcher::matchHeaders($recordedRequest, $liveRequest),
            'Sanity check: without restore() the recorded request cannot match, or nothing was hidden.'
        );

        $replayedRequest = Request::fromArray($rule->restore($onDisk)['request']);
        $this->assertTrue(
            RequestMatcher::matchHeaders($replayedRequest, $liveRequest),
            'After restore() the recorded request must match a live request carrying the real secret.'
        );
    }

    public function testAllBuildsAWildcardRuleForTheGivenScope(): void
    {
        $rule = HeaderRule::all(Scope::REQUEST);

        $redacted = $rule->redact($this->recording());

        $this->assertSame('', $redacted['request']['headers']['Authorization']);
        $this->assertSame('', $redacted['request']['headers']['X-Trace-Id']);
        $this->assertSame('application/json', $redacted['response']['headers']['Content-Type']);
    }

    public function testAllScopedToBothRedactsHeadersOnEitherSide(): void
    {
        $rule = HeaderRule::all(Scope::BOTH);

        $redacted = $rule->redact($this->recording());

        $this->assertSame('', $redacted['request']['headers']['Authorization']);
        $this->assertSame('', $redacted['request']['headers']['X-Trace-Id']);
        $this->assertSame('', $redacted['response']['headers']['Set-Cookie']);
        $this->assertSame('', $redacted['response']['headers']['Content-Type']);
    }

    public function testAllScopedToResponseLeavesRequestHeadersUntouched(): void
    {
        $rule = HeaderRule::all(Scope::RESPONSE);

        $redacted = $rule->redact($this->recording());

        $this->assertSame(self::SECRET, $redacted['request']['headers']['Authorization']);
        $this->assertSame('', $redacted['response']['headers']['Set-Cookie']);
        $this->assertSame('', $redacted['response']['headers']['Content-Type']);
    }

    public function testScopeReturnsTheConfiguredScope(): void
    {
        $rule = new HeaderRule('Authorization', null, Scope::RESPONSE);

        $this->assertSame(Scope::RESPONSE, $rule->scope());
    }

    public function testConstructionRejectsAScopeThatIsNotOneOfTheThreeKnownOnes(): void
    {
        $this->expectException(InvalidRedactionRuleException::class);

        new HeaderRule('Authorization', self::SECRET, 'requset');
    }

    /**
     * `HeaderRule::all()` never passes a source, but the constructor is public and the wildcard is a
     * documented header name — a source given alongside it would write that one value into every
     * header in scope and restore it into all of them, corrupting a multi-header recording without
     * a word of complaint.
     */
    public function testConstructionRejectsAReplacementSourceOnTheWildcardHeaderName(): void
    {
        $this->expectException(InvalidRedactionRuleException::class);
        $this->expectExceptionMessage('cannot take a replacement source');

        new HeaderRule('*', self::SECRET, Scope::REQUEST);
    }

    public function testTheWildcardHeaderNameIsStillAcceptedWithoutASource(): void
    {
        $rule = new HeaderRule('*', null, Scope::RESPONSE);

        $this->assertSame(Scope::RESPONSE, $rule->scope());
    }

    public function testIsReversibleIsTrueWhenASourceIsGiven(): void
    {
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $this->assertTrue($rule->isReversible());
    }

    public function testIsReversibleIsTrueWhenScopeIsResponseEvenWithoutASource(): void
    {
        $rule = new HeaderRule('Set-Cookie', null, Scope::RESPONSE);

        $this->assertTrue($rule->isReversible());
    }

    public function testIsReversibleIsFalseForARequestSideHeaderWithoutASource(): void
    {
        $rule = new HeaderRule('Authorization', null, Scope::REQUEST);

        $this->assertFalse($rule->isReversible());
    }

    public function testIsReversibleIsFalseWithBothScopeAndNoSource(): void
    {
        $rule = new HeaderRule('Authorization');

        $this->assertFalse($rule->isReversible());
    }

    public function testAffectedMatchersIsEmptyWhenReversible(): void
    {
        $rule = new HeaderRule('Authorization', self::SECRET, Scope::REQUEST);

        $this->assertSame([], $rule->affectedMatchers());
    }

    public function testAffectedMatchersIsHeadersWhenIrreversible(): void
    {
        $rule = new HeaderRule('Authorization', null, Scope::REQUEST);

        $this->assertSame(['headers'], $rule->affectedMatchers());
    }

    public function testAffectedMatchersIsEmptyForAResponseSideHeaderWithoutASource(): void
    {
        $rule = new HeaderRule('Set-Cookie', null, Scope::RESPONSE);

        $this->assertSame([], $rule->affectedMatchers());
    }
}
