<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\RequestMatcher;
use VCR\Storage\Redaction\MissingSecretException;
use VCR\Storage\Redaction\PlaceholderCollisionException;
use VCR\Storage\Redaction\Rule\PostFieldRule;
use VCR\Storage\Redaction\Scope;

final class PostFieldRuleTest extends TestCase
{
    /**
     * Pinned as a literal rather than read back from Placeholder: the format ends up in committed
     * cassettes, so a change to it is a change to what users see on disk and has to be deliberate.
     */
    private const PASSWORD_PLACEHOLDER = '<<REDACTED:POST_FIELD:password>>';

    private const SECRET = 'hunter2';

    /**
     * @return array<string,mixed>
     */
    private function recording(): array
    {
        return [
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.example.com/login',
                'headers' => [],
                'post_fields' => ['user' => 'alice', 'password' => self::SECRET],
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
            ],
            'index' => 0,
        ];
    }

    public function testRedactWritesThePlaceholderRatherThanTheSecretItself(): void
    {
        $rule = new PostFieldRule('password', self::SECRET);

        $redacted = $rule->redact($this->recording());

        $this->assertSame(self::PASSWORD_PLACEHOLDER, $redacted['request']['post_fields']['password']);
        $this->assertSame('alice', $redacted['request']['post_fields']['user']);
    }

    public function testThePlaceholderIsDerivedFromTheFieldNameSoReRecordingIsByteIdentical(): void
    {
        $rule = new PostFieldRule('password', self::SECRET);

        $this->assertSame($rule->redact($this->recording()), $rule->redact($this->recording()));
    }

    public function testRedactIsANoOpWhenTheFieldIsNotPresent(): void
    {
        $recording = $this->recording();
        $rule = new PostFieldRule('missing-field', 'redacted');

        $redacted = $rule->redact($recording);

        $this->assertSame($recording, $redacted);
    }

    public function testRestoreWritesTheRealSecretBackIntoTheField(): void
    {
        $rule = new PostFieldRule('password', self::SECRET);

        $restored = $rule->restore($rule->redact($this->recording()));

        $this->assertSame(self::SECRET, $restored['request']['post_fields']['password']);
    }

    public function testRedactAndRestoreRoundTripBackToTheOriginalRecording(): void
    {
        $recording = $this->recording();
        $rule = new PostFieldRule('password', self::SECRET);

        $this->assertSame($recording, $rule->restore($rule->redact($recording)));
    }

    public function testRestoreIsANoOpWithoutASource(): void
    {
        $recording = $this->recording();
        $rule = new PostFieldRule('password');

        $restored = $rule->restore($recording);

        $this->assertSame($recording, $restored);
    }

    public function testRestoreIsANoOpWhenTheFieldIsNotPresent(): void
    {
        $recording = $this->recording();
        $rule = new PostFieldRule('missing-field', self::SECRET);

        $this->assertSame($recording, $rule->restore($recording));
    }

    public function testRedactBlanksTheFieldValueWithoutASource(): void
    {
        $rule = new PostFieldRule('password');

        $redacted = $rule->redact($this->recording());

        $this->assertSame('', $redacted['request']['post_fields']['password']);
        $this->assertSame('alice', $redacted['request']['post_fields']['user']);
    }

    public function testRedactThrowsMissingSecretExceptionWhenSourceResolvesToEmptyString(): void
    {
        $rule = new PostFieldRule('password', '');

        $this->expectException(MissingSecretException::class);
        $this->expectExceptionMessage(self::PASSWORD_PLACEHOLDER);

        $rule->redact($this->recording());
    }

    public function testRedactThrowsPlaceholderCollisionExceptionWhenTheValueAlreadyCarriesThePlaceholder(): void
    {
        $recording = $this->recording();
        $recording['request']['post_fields']['password'] = self::PASSWORD_PLACEHOLDER;
        $rule = new PostFieldRule('password', self::SECRET);

        $this->expectException(PlaceholderCollisionException::class);
        $this->expectExceptionMessage('request.post_fields.password');

        $rule->redact($recording);
    }

    /**
     * The secret must be gone from what reaches the disk, and back in place by the time the real
     * `post_fields` matcher compares the recording against a live request.
     */
    public function testTheRedactRestoreRoundTripSatisfiesTheRealPostFieldsMatcherAgainstALiveRequest(): void
    {
        $recording = $this->recording();
        $rule = new PostFieldRule('password', self::SECRET);

        $onDisk = $rule->redact($recording);
        $this->assertStringNotContainsString(self::SECRET, (string) json_encode($onDisk['request']));

        $liveRequest = Request::fromArray($recording['request']);
        $this->assertFalse(
            RequestMatcher::matchPostFields(Request::fromArray($onDisk['request']), $liveRequest),
            'Sanity check: without restore() the recorded request cannot match, or nothing was hidden.'
        );

        $this->assertTrue(
            RequestMatcher::matchPostFields(Request::fromArray($rule->restore($onDisk)['request']), $liveRequest)
        );
    }

    public function testScopeIsAlwaysRequest(): void
    {
        $rule = new PostFieldRule('password', self::SECRET);

        $this->assertSame(Scope::REQUEST, $rule->scope());
    }

    public function testIsReversibleIsTrueWhenASourceIsGiven(): void
    {
        $rule = new PostFieldRule('password', self::SECRET);

        $this->assertTrue($rule->isReversible());
    }

    public function testIsReversibleIsFalseWithoutASource(): void
    {
        $rule = new PostFieldRule('password');

        $this->assertFalse($rule->isReversible());
    }

    public function testAffectedMatchersIsEmptyWhenReversible(): void
    {
        $rule = new PostFieldRule('password', self::SECRET);

        $this->assertSame([], $rule->affectedMatchers());
    }

    public function testAffectedMatchersIsPostFieldsWhenIrreversible(): void
    {
        $rule = new PostFieldRule('password');

        $this->assertSame(['post_fields'], $rule->affectedMatchers());
    }
}
