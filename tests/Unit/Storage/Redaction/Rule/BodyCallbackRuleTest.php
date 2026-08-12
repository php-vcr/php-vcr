<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\InvalidRedactionRuleException;
use VCR\Storage\Redaction\Rule\BodyCallbackRule;
use VCR\Storage\Redaction\Scope;

final class BodyCallbackRuleTest extends TestCase
{
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
                'body' => '{"user":"alice"}',
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
                'body' => '{"token":"abc-123"}',
            ],
            'index' => 0,
        ];
    }

    public function testRedactAppliesTheCallbackToTheRequestBodyOnlyWhenScopedToRequest(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => strtoupper($body), Scope::REQUEST);

        $redacted = $rule->redact($this->recording());

        $this->assertSame('{"USER":"ALICE"}', $redacted['request']['body']);
        $this->assertSame('{"token":"abc-123"}', $redacted['response']['body']);
    }

    public function testRedactAppliesTheCallbackToTheResponseBodyOnlyWhenScopedToResponse(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => strtoupper($body), Scope::RESPONSE);

        $redacted = $rule->redact($this->recording());

        $this->assertSame('{"user":"alice"}', $redacted['request']['body']);
        $this->assertSame('{"TOKEN":"ABC-123"}', $redacted['response']['body']);
    }

    public function testRedactAppliesTheCallbackToBothBodiesWhenScopedToBoth(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => strtoupper($body), Scope::BOTH);

        $redacted = $rule->redact($this->recording());

        $this->assertSame('{"USER":"ALICE"}', $redacted['request']['body']);
        $this->assertSame('{"TOKEN":"ABC-123"}', $redacted['response']['body']);
    }

    public function testRedactIsANoOpWhenTheScopedBodyIsMissing(): void
    {
        $recording = $this->recording();
        unset($recording['request']['body']);
        $rule = new BodyCallbackRule(static fn (string $body): string => strtoupper($body), Scope::REQUEST);

        $redacted = $rule->redact($recording);

        $this->assertSame($recording, $redacted);
    }

    public function testRestoreIsANoOpAndDoesNotInvokeTheCallback(): void
    {
        $recording = $this->recording();
        $rule = new BodyCallbackRule(function (string $body): string {
            $this->fail('restore() must not invoke the callback.');
        }, Scope::BOTH);

        $restored = $rule->restore($recording);

        $this->assertSame($recording, $restored);
    }

    public function testConstructionRejectsAScopeThatIsNotOneOfTheThreeKnownOnes(): void
    {
        $this->expectException(InvalidRedactionRuleException::class);

        new BodyCallbackRule(static fn (string $body): string => $body, 'requset');
    }

    public function testScopeReturnsTheConfiguredScope(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => $body, Scope::RESPONSE);

        $this->assertSame(Scope::RESPONSE, $rule->scope());
    }

    public function testIsReversibleIsAlwaysFalse(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => $body, Scope::BOTH);

        $this->assertFalse($rule->isReversible());
    }

    public function testAffectedMatchersIsBodyWhenScopeIsRequest(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => $body, Scope::REQUEST);

        $this->assertSame(['body'], $rule->affectedMatchers());
    }

    public function testAffectedMatchersIsBodyWhenScopeIsBoth(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => $body, Scope::BOTH);

        $this->assertSame(['body'], $rule->affectedMatchers());
    }

    public function testAffectedMatchersIsEmptyWhenScopeIsResponse(): void
    {
        $rule = new BodyCallbackRule(static fn (string $body): string => $body, Scope::RESPONSE);

        $this->assertSame([], $rule->affectedMatchers());
    }
}
