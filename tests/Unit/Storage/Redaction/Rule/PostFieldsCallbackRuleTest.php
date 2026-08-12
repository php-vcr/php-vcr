<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\Rule\PostFieldsCallbackRule;
use VCR\Storage\Redaction\Scope;

final class PostFieldsCallbackRuleTest extends TestCase
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
                'body' => '{}',
                'post_fields' => ['user' => 'alice', 'password' => 'hunter2'],
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
            ],
            'index' => 0,
        ];
    }

    public function testRedactAppliesTheCallbackToTheWholePostFieldsArray(): void
    {
        $rule = new PostFieldsCallbackRule(
            static fn (array $postFields): array => array_map(static fn ($value) => strtoupper((string) $value), $postFields)
        );

        $redacted = $rule->redact($this->recording());

        $this->assertSame(['user' => 'ALICE', 'password' => 'HUNTER2'], $redacted['request']['post_fields']);
    }

    public function testRedactLeavesTheRequestBodyAndOtherFieldsUntouched(): void
    {
        $recording = $this->recording();
        $rule = new PostFieldsCallbackRule(static fn (array $postFields): array => []);

        $redacted = $rule->redact($recording);

        $this->assertSame($recording['request']['body'], $redacted['request']['body']);
        $this->assertSame($recording['request']['method'], $redacted['request']['method']);
        $this->assertSame($recording['response'], $redacted['response']);
    }

    public function testRedactIsANoOpWhenPostFieldsIsMissing(): void
    {
        $recording = $this->recording();
        unset($recording['request']['post_fields']);
        $rule = new PostFieldsCallbackRule(static fn (array $postFields): array => ['injected' => 'value']);

        $redacted = $rule->redact($recording);

        $this->assertSame($recording, $redacted);
    }

    public function testRestoreIsANoOpAndDoesNotInvokeTheCallback(): void
    {
        $recording = $this->recording();
        $rule = new PostFieldsCallbackRule(function (array $postFields): array {
            $this->fail('restore() must not invoke the callback.');
        });

        $restored = $rule->restore($recording);

        $this->assertSame($recording, $restored);
    }

    public function testScopeIsAlwaysRequest(): void
    {
        $rule = new PostFieldsCallbackRule(static fn (array $postFields): array => $postFields);

        $this->assertSame(Scope::REQUEST, $rule->scope());
    }

    public function testIsReversibleIsAlwaysFalse(): void
    {
        $rule = new PostFieldsCallbackRule(static fn (array $postFields): array => $postFields);

        $this->assertFalse($rule->isReversible());
    }

    public function testAffectedMatchersIsAlwaysPostFields(): void
    {
        $rule = new PostFieldsCallbackRule(static fn (array $postFields): array => $postFields);

        $this->assertSame(['post_fields'], $rule->affectedMatchers());
    }
}
