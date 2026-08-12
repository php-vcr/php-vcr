<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\RequestMatcher;
use VCR\Storage\Redaction\MissingSecretException;
use VCR\Storage\Redaction\PlaceholderCollisionException;
use VCR\Storage\Redaction\Rule\HostRule;
use VCR\Storage\Redaction\Rule\QueryParameterRule;
use VCR\Storage\Redaction\Scope;

final class HostRuleTest extends TestCase
{
    /**
     * Pinned as a literal rather than read back from Placeholder: the format ends up in committed
     * cassettes, so a change to it is a change to what users see on disk and has to be deliberate.
     * Unlike the other rules' placeholders this one has to stay a valid hostname, since it is
     * written into `request.url`.
     */
    private const HOST_PLACEHOLDER = 'redacted-host.invalid';

    private const REAL_HOST = 'api.example.com';

    /**
     * @return array<string,mixed>
     */
    private function recording(): array
    {
        return [
            'request' => [
                'method' => 'GET',
                'url' => 'https://api.example.com:8443/status?ping=1',
                'headers' => ['Host' => 'api.example.com:8443', 'X-Trace-Id' => 'abc-123'],
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
            ],
            'index' => 0,
        ];
    }

    public function testRedactWritesThePlaceholderHostRatherThanTheRealOne(): void
    {
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($this->recording());

        $this->assertSame('https://redacted-host.invalid:8443/status?ping=1', $redacted['request']['url']);
        $this->assertSame('redacted-host.invalid:8443', $redacted['request']['headers']['Host']);
        $this->assertStringNotContainsString(self::REAL_HOST, (string) json_encode($redacted['request']));
    }

    public function testThePlaceholderHostStaysParseableSoLaterRulesStillSeeAUsableUrl(): void
    {
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($this->recording());

        $this->assertSame(self::HOST_PLACEHOLDER, parse_url($redacted['request']['url'], \PHP_URL_HOST));
        $this->assertSame(8443, parse_url($redacted['request']['url'], \PHP_URL_PORT));
    }

    public function testRedactLeavesTheRestOfTheUrlUntouched(): void
    {
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($this->recording());

        $this->assertStringContainsString(':8443', $redacted['request']['url']);
        $this->assertStringContainsString('/status', $redacted['request']['url']);
        $this->assertStringContainsString('?ping=1', $redacted['request']['url']);
    }

    public function testRedactIsANoOpOnTheHostHeaderWhenAbsent(): void
    {
        $recording = $this->recording();
        unset($recording['request']['headers']['Host']);
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($recording);

        $this->assertArrayNotHasKey('Host', $redacted['request']['headers']);
        $this->assertSame('https://redacted-host.invalid:8443/status?ping=1', $redacted['request']['url']);
    }

    public function testRedactMatchesTheHostHeaderNameCaseInsensitively(): void
    {
        $recording = $this->recording();
        $recording['request']['headers'] = ['host' => 'api.example.com'];
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($recording);

        $this->assertSame(self::HOST_PLACEHOLDER, $redacted['request']['headers']['host']);
    }

    public function testRestoreWritesTheRealHostBackIntoBothLocations(): void
    {
        $rule = new HostRule(self::REAL_HOST);

        $restored = $rule->restore($rule->redact($this->recording()));

        $this->assertSame('https://api.example.com:8443/status?ping=1', $restored['request']['url']);
        $this->assertSame('api.example.com:8443', $restored['request']['headers']['Host']);
    }

    public function testRedactAndRestoreRoundTripBackToTheOriginalRecording(): void
    {
        $recording = $this->recording();
        $rule = new HostRule(self::REAL_HOST);

        $this->assertSame($recording, $rule->restore($rule->redact($recording)));
    }

    /**
     * The URL and the `Host` header need not spell the host identically — hostnames are
     * case-insensitive, and the header routinely carries a port the URL's host component does not.
     * A byte-exact search missed such a header and fell through to a wholesale overwrite, which
     * dropped the port and failed the `headers` matcher on replay.
     */
    public function testAHostHeaderThatSpellsTheHostDifferentlyKeepsItsPortThroughTheRoundTrip(): void
    {
        $recording = $this->recording();
        $recording['request']['url'] = 'https://API.Example.com:8443/status?ping=1';
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($recording);

        $this->assertSame('redacted-host.invalid:8443', $redacted['request']['headers']['Host']);
        $this->assertStringNotContainsString('Example.com', (string) json_encode($redacted['request']));

        $restored = $rule->restore($redacted);

        $this->assertSame('api.example.com:8443', $restored['request']['headers']['Host']);
    }

    /**
     * Same divergence, checked against the matcher it used to break: the live request carries the
     * host the source names, and the recorded `Host` header has to come back equal to it.
     */
    public function testAHostHeaderThatSpellsTheHostDifferentlySatisfiesTheRealHeadersMatcher(): void
    {
        $recording = $this->recording();
        $recording['request']['url'] = 'https://API.Example.com:8443/status?ping=1';
        $rule = new HostRule(self::REAL_HOST);

        $liveRequest = Request::fromArray($recording['request']);
        $replayedRequest = Request::fromArray($rule->restore($rule->redact($recording))['request']);

        $this->assertTrue(RequestMatcher::matchHeaders($replayedRequest, $liveRequest));
    }

    public function testAHostHeaderCarryingAPortTheUrlDoesNotKeepsThatPort(): void
    {
        $recording = $this->recording();
        $recording['request']['url'] = 'https://api.example.com/status?ping=1';
        $recording['request']['headers']['Host'] = 'api.example.com:8443';
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($recording);

        $this->assertSame('redacted-host.invalid:8443', $redacted['request']['headers']['Host']);
        $this->assertSame($recording, $rule->restore($redacted));
    }

    public function testAHostHeaderThatDoesNotCarryTheHostIsOverwrittenWholesaleRatherThanLeftLeaking(): void
    {
        $recording = $this->recording();
        $recording['request']['headers']['Host'] = 'proxy.internal';
        $rule = new HostRule(self::REAL_HOST);

        $redacted = $rule->redact($recording);

        $this->assertSame(self::HOST_PLACEHOLDER, $redacted['request']['headers']['Host']);
    }

    public function testRestoreIsANoOpWithoutASource(): void
    {
        $recording = $this->recording();
        $rule = new HostRule();

        $restored = $rule->restore($recording);

        $this->assertSame($recording, $restored);
    }

    public function testRedactReplacesTheHostWithAFixedPlaceholderWithoutASource(): void
    {
        $rule = new HostRule();

        $redacted = $rule->redact($this->recording());

        $this->assertSame('https://redacted:8443/status?ping=1', $redacted['request']['url']);
        $this->assertSame('redacted', $redacted['request']['headers']['Host']);
    }

    public function testRedactWithoutASourceProducesAUrlThatRequestFromArrayCanParse(): void
    {
        $recording = $this->recording();
        unset($recording['request']['headers']['Host']);
        $rule = new HostRule();

        $redacted = $rule->redact($recording);

        $request = Request::fromArray($redacted['request']);

        $this->assertSame('redacted:8443', $request->getHost());
    }

    public function testComposesWithAnotherRuleAppliedAfterwardsOnTheSameUrl(): void
    {
        $recording = $this->recording();
        $recording['request']['url'] = 'https://api.example.com:8443/status?ping=1&token=secret';
        $hostRule = new HostRule();
        $queryRule = new QueryParameterRule('token', 'secret');

        $afterHost = $hostRule->redact($recording);
        $afterQuery = $queryRule->redact($afterHost);

        $this->assertStringContainsString('token=<<REDACTED:QUERY_PARAMETER:token>>', $afterQuery['request']['url']);
        $this->assertStringContainsString('redacted:8443', $afterQuery['request']['url']);
    }

    public function testTheReversiblePlaceholderAlsoLeavesALaterRuleAUsableUrl(): void
    {
        $recording = $this->recording();
        $recording['request']['url'] = 'https://api.example.com:8443/status?ping=1&token=secret';
        $afterHost = (new HostRule(self::REAL_HOST))->redact($recording);

        $afterQuery = (new QueryParameterRule('token', 'secret'))->redact($afterHost);

        $this->assertStringContainsString('token=<<REDACTED:QUERY_PARAMETER:token>>', $afterQuery['request']['url']);
        $this->assertStringContainsString('redacted-host.invalid:8443', $afterQuery['request']['url']);
    }

    public function testRedactThrowsMissingSecretExceptionWhenSourceResolvesToEmptyString(): void
    {
        $rule = new HostRule('');

        $this->expectException(MissingSecretException::class);
        $this->expectExceptionMessage(self::HOST_PLACEHOLDER);

        $rule->redact($this->recording());
    }

    public function testRedactThrowsPlaceholderCollisionExceptionWhenTheUrlAlreadyCarriesThePlaceholder(): void
    {
        $recording = $this->recording();
        $recording['request']['url'] = 'https://redacted-host.invalid:8443/status?ping=1';
        $rule = new HostRule(self::REAL_HOST);

        $this->expectException(PlaceholderCollisionException::class);
        $this->expectExceptionMessage('request.url');

        $rule->redact($recording);
    }

    /**
     * The rule rewrites two locations at once, so both matchers it would otherwise invalidate have
     * to be satisfied again after restore() — including the port the Host header carries, which the
     * rule does not own and must not lose.
     */
    public function testTheRedactRestoreRoundTripSatisfiesTheRealHostAndHeadersMatchersAgainstALiveRequest(): void
    {
        $recording = $this->recording();
        $rule = new HostRule(self::REAL_HOST);

        $onDisk = $rule->redact($recording);
        $this->assertStringNotContainsString(self::REAL_HOST, (string) json_encode($onDisk['request']));

        $liveRequest = Request::fromArray($recording['request']);
        $recordedRequest = Request::fromArray($onDisk['request']);
        $this->assertFalse(RequestMatcher::matchHost($recordedRequest, $liveRequest));
        $this->assertFalse(RequestMatcher::matchHeaders($recordedRequest, $liveRequest));

        $replayedRequest = Request::fromArray($rule->restore($onDisk)['request']);
        $this->assertTrue(RequestMatcher::matchHost($replayedRequest, $liveRequest));
        $this->assertTrue(RequestMatcher::matchHeaders($replayedRequest, $liveRequest));
    }

    public function testScopeIsAlwaysRequest(): void
    {
        $rule = new HostRule(self::REAL_HOST);

        $this->assertSame(Scope::REQUEST, $rule->scope());
    }

    public function testIsReversibleIsTrueWhenASourceIsGiven(): void
    {
        $rule = new HostRule(self::REAL_HOST);

        $this->assertTrue($rule->isReversible());
    }

    public function testIsReversibleIsFalseWithoutASource(): void
    {
        $rule = new HostRule();

        $this->assertFalse($rule->isReversible());
    }

    public function testAffectedMatchersIsEmptyWhenReversible(): void
    {
        $rule = new HostRule(self::REAL_HOST);

        $this->assertSame([], $rule->affectedMatchers());
    }

    public function testAffectedMatchersIsHostAndHeadersWhenIrreversible(): void
    {
        $rule = new HostRule();

        $this->assertSame(['host', 'headers'], $rule->affectedMatchers());
    }
}
