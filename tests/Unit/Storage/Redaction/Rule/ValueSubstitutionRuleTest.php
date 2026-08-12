<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\InvalidRedactionRuleException;
use VCR\Storage\Redaction\MissingSecretException;
use VCR\Storage\Redaction\PlaceholderCollisionException;
use VCR\Storage\Redaction\Rule\ValueSubstitutionRule;
use VCR\Storage\Redaction\Scope;

final class ValueSubstitutionRuleTest extends TestCase
{
    private const SECRET = 'hunter2';

    /**
     * @return array<string,mixed>
     */
    private function recording(string $secret = self::SECRET): array
    {
        return [
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.example.com/login',
                'headers' => ['Authorization' => 'Bearer '.$secret],
                'body' => '{"password":"'.$secret.'"}',
                'post_files' => [
                    ['fieldName' => 'file', 'filename' => 'secret.txt', 'content' => 'token='.$secret],
                ],
                'post_fields' => ['password' => $secret],
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
                'headers' => ['Set-Cookie' => 'session='.$secret],
                'body' => 'welcome '.$secret,
                'curl_info' => ['request_header' => 'Authorization: Bearer '.$secret],
            ],
            'index' => 0,
        ];
    }

    /**
     * @param array<string,mixed> $recording
     */
    private function assertContainsPlaceholderEverywhere(array $recording, string $placeholder, string $secret): void
    {
        $this->assertStringNotContainsString($secret, $recording['request']['headers']['Authorization']);
        $this->assertStringNotContainsString($secret, $recording['request']['body']);
        $this->assertStringNotContainsString($secret, $recording['request']['post_files'][0]['content']);
        $this->assertStringNotContainsString($secret, $recording['request']['post_fields']['password']);
        $this->assertStringNotContainsString($secret, $recording['response']['headers']['Set-Cookie']);
        $this->assertStringNotContainsString($secret, $recording['response']['body']);
        $this->assertStringNotContainsString($secret, $recording['response']['curl_info']['request_header']);

        $this->assertStringContainsString($placeholder, $recording['request']['headers']['Authorization']);
        $this->assertStringContainsString($placeholder, $recording['request']['body']);
        $this->assertStringContainsString($placeholder, $recording['request']['post_files'][0]['content']);
        $this->assertSame($placeholder, $recording['request']['post_fields']['password']);
        $this->assertStringContainsString($placeholder, $recording['response']['headers']['Set-Cookie']);
        $this->assertStringContainsString($placeholder, $recording['response']['body']);
        $this->assertStringContainsString($placeholder, $recording['response']['curl_info']['request_header']);
    }

    public function testLiteralSourceRoundTrips(): void
    {
        $recording = $this->recording();
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', self::SECRET);

        $redacted = $rule->redact($recording);
        $this->assertContainsPlaceholderEverywhere($redacted, '{{PASSWORD}}', self::SECRET);

        $restored = $rule->restore($redacted);
        $this->assertSame($recording, $restored);
    }

    public function testNoArgumentCallableSourceRoundTrips(): void
    {
        $recording = $this->recording();
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', static fn (): string => ValueSubstitutionRuleTest::SECRET);

        $redacted = $rule->redact($recording);
        $this->assertContainsPlaceholderEverywhere($redacted, '{{PASSWORD}}', self::SECRET);

        $restored = $rule->restore($redacted);
        $this->assertSame($recording, $restored);
    }

    public function testRecordingAwareCallableSourceRoundTrips(): void
    {
        $recording = $this->recording();
        // The derived value (the status code, as a string) also appears verbatim as a string leaf
        // elsewhere, so the round-trip actually exercises a substitution rather than being a no-op.
        $recording['response']['headers']['X-Order-Status'] = 'status:200';
        $rule = new ValueSubstitutionRule('{{ORDER_STATUS_CODE}}', static fn (array $recording): string => (string) $recording['response']['status']['code']);

        $redacted = $rule->redact($recording);
        $this->assertSame(200, $redacted['response']['status']['code']);
        $this->assertStringNotContainsString('200', $redacted['response']['headers']['X-Order-Status']);
        $this->assertSame('status:{{ORDER_STATUS_CODE}}', $redacted['response']['headers']['X-Order-Status']);

        $restored = $rule->restore($redacted);
        $this->assertSame($recording, $restored);
    }

    public function testCallableSourceWithUnrelatedOptionalParameterIsTreatedAsNoArgument(): void
    {
        $recording = $this->recording();
        $rule = new ValueSubstitutionRule(
            '{{PASSWORD}}',
            static fn (string $unrelated = self::SECRET): string => $unrelated
        );

        $redacted = $rule->redact($recording);
        $this->assertContainsPlaceholderEverywhere($redacted, '{{PASSWORD}}', self::SECRET);

        $restored = $rule->restore($redacted);
        $this->assertSame($recording, $restored);
    }

    public function testRedactThrowsMissingSecretExceptionWhenSourceResolvesToNull(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', static fn (): ?string => null);

        $this->expectException(MissingSecretException::class);

        $rule->redact($this->recording());
    }

    public function testRestoreThrowsMissingSecretExceptionWhenSourceResolvesToNull(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', static fn (): ?string => null);

        $this->expectException(MissingSecretException::class);

        $rule->restore($this->recording());
    }

    public function testRedactThrowsMissingSecretExceptionWhenSourceResolvesToEmptyString(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', '');

        $this->expectException(MissingSecretException::class);

        $rule->redact($this->recording());
    }

    public function testRestoreThrowsMissingSecretExceptionWhenSourceResolvesToEmptyString(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', '');

        $this->expectException(MissingSecretException::class);

        $rule->restore($this->recording());
    }

    public function testConstructionRejectsAnEmptyPlaceholder(): void
    {
        $this->expectException(InvalidRedactionRuleException::class);

        new ValueSubstitutionRule('', self::SECRET);
    }

    /**
     * `getenv()` returns false for an unset variable, which is exactly the documented
     * `filterSensitiveData('<<AUTH_TOKEN>>', getenv('API_TOKEN'))` shape on a CI runner without the
     * secret configured — it has to name the placeholder, not blow up with a TypeError.
     */
    public function testAnUnsetEnvironmentVariableSourceRaisesMissingSecretExceptionNotATypeError(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', getenv('VCR_TEST_DEFINITELY_UNSET_VARIABLE'));

        $this->expectException(MissingSecretException::class);
        $this->expectExceptionMessage('{{PASSWORD}}');

        $rule->redact($this->recording());
    }

    public function testRedactThrowsPlaceholderCollisionExceptionWhenPlaceholderAlreadyPresent(): void
    {
        $recording = $this->recording();
        $recording['response']['body'] = 'welcome '.self::SECRET.' {{PASSWORD}}';
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', self::SECRET);

        $this->expectException(PlaceholderCollisionException::class);
        $this->expectExceptionMessage('{{PASSWORD}}');

        $rule->redact($recording);
    }

    public function testTwoRulesSharingTheSameSecretWithDifferentPlaceholdersDoNotCorruptDataWhenChained(): void
    {
        $recording = $this->recording();
        $ruleA = new ValueSubstitutionRule('{{SECRET_A}}', self::SECRET);
        $ruleB = new ValueSubstitutionRule('{{SECRET_B}}', self::SECRET);

        $redacted = $ruleB->redact($ruleA->redact($recording));

        $this->assertStringNotContainsString(self::SECRET, $redacted['response']['body']);
        $this->assertStringContainsString('{{SECRET_A}}', $redacted['response']['body']);
        $this->assertStringNotContainsString('{{SECRET_B}}', $redacted['response']['body']);

        $restored = $ruleA->restore($ruleB->restore($redacted));

        $this->assertSame($recording, $restored);
    }

    public function testScopeIsBoth(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', self::SECRET);

        $this->assertSame(Scope::BOTH, $rule->scope());
    }

    public function testIsReversibleIsTrue(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', self::SECRET);

        $this->assertTrue($rule->isReversible());
    }

    public function testAffectedMatchersIsEmpty(): void
    {
        $rule = new ValueSubstitutionRule('{{PASSWORD}}', self::SECRET);

        $this->assertSame([], $rule->affectedMatchers());
    }
}
