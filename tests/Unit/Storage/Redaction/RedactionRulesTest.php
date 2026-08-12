<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Configuration;
use VCR\Request;
use VCR\Storage\Redaction\MissingReplacementException;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\RedactionRules;
use VCR\Storage\Redaction\Rule\HeaderRule;
use VCR\Storage\Redaction\Rule\HostRule;
use VCR\Storage\Redaction\Rule\PostFieldRule;
use VCR\Storage\Redaction\Rule\QueryParameterRule;
use VCR\Storage\Redaction\Rule\ValueSubstitutionRule;
use VCR\Storage\Redaction\Scope;

final class RedactionRulesTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function defaultMatchers(): array
    {
        return ['method', 'url', 'host', 'headers', 'body', 'post_fields', 'query_string', 'soap_operation'];
    }

    public function testHeaderWithNoSourceAndNoOptInThrowsMissingReplacementException(): void
    {
        $this->expectException(MissingReplacementException::class);

        RedactionRules::create()->header('Authorization');
    }

    public function testHeaderWithNoSourceSucceedsAfterAllowIrreversibleRequestRedaction(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->header('Authorization');

        $this->assertCount(1, $rules->rules());
    }

    public function testResponseOnlyIrreversibleHeaderNeverThrowsRegardlessOfOptIn(): void
    {
        $rules = RedactionRules::create()->header('Set-Cookie', null, Scope::RESPONSE);

        $this->assertCount(1, $rules->rules());
    }

    public function testResponseOnlyIrreversibleHeaderNeverThrowsEvenAfterOptIn(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->header('Set-Cookie', null, Scope::RESPONSE);

        $this->assertCount(1, $rules->rules());
    }

    public function testSafeAndInvalidatedRequestMatchersPartitionTheFullDefaultSet(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->header('Authorization')
            ->queryParameter('token');

        $safe = $rules->safeRequestMatchers();
        $invalidated = $rules->invalidatedRequestMatchers();

        $this->assertSame(['headers', 'query_string'], $invalidated);
        $this->assertSame(
            array_values(array_diff($this->defaultMatchers(), $invalidated)),
            $safe
        );
        $union = array_unique(array_merge($safe, $invalidated));
        sort($union);
        $expected = $this->defaultMatchers();
        sort($expected);
        $this->assertSame($expected, $union);
        $this->assertSame([], array_intersect($safe, $invalidated));
    }

    public function testZeroIrreversibleRulesLeavesFullDefaultSetSafe(): void
    {
        $rules = RedactionRules::create()
            ->filterSensitiveData('REDACTED', 'secret-value')
            ->header('Authorization', 'Bearer secret-token');

        $this->assertSame([], $rules->invalidatedRequestMatchers());
        $this->assertSame($this->defaultMatchers(), $rules->safeRequestMatchers());
    }

    /**
     * @return array<string,mixed>
     */
    private function hostRecording(): array
    {
        return [
            'request' => [
                'method' => 'GET',
                'url' => 'https://api.example.com:8443/status?ping=1',
                'headers' => ['Host' => 'api.example.com:8443'],
            ],
            'response' => ['status' => ['code' => 200, 'message' => 'OK']],
            'index' => 0,
        ];
    }

    /**
     * Runs the recording through every rule the way RedactingStorage does, then compares the result
     * against a live request built from the untouched recording, using the very matcher callbacks
     * Configuration hands to Cassette::playback().
     *
     * @param list<string> $matchers
     */
    private function matchesThroughRealMatchers(RedactionRules $rules, array $matchers): bool
    {
        $recording = $this->hostRecording();
        $onDisk = $recording;

        foreach ($rules->rules() as $rule) {
            $onDisk = $rule->redact($onDisk);
        }

        $replayed = $onDisk;
        foreach (array_reverse($rules->rules()) as $rule) {
            $replayed = $rule->restore($replayed);
        }

        $configuration = new Configuration();
        $configuration->enableRequestMatchers($matchers);

        return Request::fromArray($replayed['request'])
            ->matches(Request::fromArray($recording['request']), $configuration->getRequestMatchers());
    }

    /**
     * HostRule is the only built-in that invalidates more than one matcher, which makes it the case
     * where an inaccurate affectedMatchers() does the most damage. Asserting the list is not enough
     * — the review that caught this twice was fooled by exactly that — so this drives the reported
     * lists through the real matcher callbacks: everything safeRequestMatchers() keeps must actually
     * still match, and the full default set must not.
     */
    public function testAnIrreversibleMultiKeyRuleLeavesExactlyTheMatchersItKeepsWorking(): void
    {
        $rules = RedactionRules::create()->allowIrreversibleRequestRedaction()->host();

        $this->assertSame(['host', 'headers'], $rules->invalidatedRequestMatchers());
        $this->assertSame(
            ['method', 'url', 'body', 'post_fields', 'query_string', 'soap_operation'],
            $rules->safeRequestMatchers()
        );

        $this->assertFalse(
            $this->matchesThroughRealMatchers($rules, $this->defaultMatchers()),
            'Sanity check: the full default set must break, otherwise the rule under-reports nothing.'
        );
        $this->assertTrue(
            $this->matchesThroughRealMatchers($rules, $rules->safeRequestMatchers()),
            'Every matcher safeRequestMatchers() keeps must still match the redacted recording.'
        );
    }

    /**
     * The same rule given a source reports no invalidated matchers at all — and this time the full
     * default set really does still match, because restore() puts the real host back.
     */
    public function testTheSameMultiKeyRuleGivenASourceKeepsEveryDefaultMatcherWorking(): void
    {
        $rules = RedactionRules::create()->host('api.example.com');

        $this->assertSame([], $rules->invalidatedRequestMatchers());
        $this->assertSame($this->defaultMatchers(), $rules->safeRequestMatchers());
        $this->assertTrue($this->matchesThroughRealMatchers($rules, $this->defaultMatchers()));
    }

    public function testRulesReturnsRulesInRegistrationOrder(): void
    {
        $first = new ValueSubstitutionRule('FIRST', 'secret-one');
        $second = new HeaderRule('Authorization', 'replacement-token');
        $third = new QueryParameterRule('token', 'replacement-query-token');

        $rules = RedactionRules::create()
            ->add($first)
            ->add($second)
            ->add($third);

        $this->assertSame([$first, $second, $third], $rules->rules());
    }

    /**
     * A stand-in for a caller-written rule, so the tests around add()'s reversibility contract can
     * vary the three things that contract actually reads — scope, reversibility and the matchers
     * the rule invalidates — without repeating the interface five times.
     *
     * @param Scope::REQUEST|Scope::RESPONSE|Scope::BOTH $scope
     * @param list<string>                               $matchers what the rule claims to invalidate
     */
    private function customRule(string $scope, array $matchers = [], bool $reversible = false): RedactionRuleInterface
    {
        return new class($scope, $matchers, $reversible) implements RedactionRuleInterface {
            /**
             * @param Scope::REQUEST|Scope::RESPONSE|Scope::BOTH $ruleScope
             * @param list<string>                               $matchers
             */
            public function __construct(
                private string $ruleScope,
                private array $matchers,
                private bool $reversible
            ) {
            }

            public function scope(): string
            {
                return $this->ruleScope;
            }

            public function isReversible(): bool
            {
                return $this->reversible;
            }

            public function affectedMatchers(): array
            {
                return $this->matchers;
            }

            public function redact(array $recording): array
            {
                return $recording;
            }

            public function restore(array $recording): array
            {
                return $recording;
            }
        };
    }

    public function testAddRejectsACustomIrreversibleRequestScopedRuleWithoutOptIn(): void
    {
        $this->expectException(MissingReplacementException::class);

        RedactionRules::create()->add($this->customRule(Scope::REQUEST, ['body']));
    }

    public function testAddAllowsACustomIrreversibleRequestScopedRuleAfterOptIn(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->add($this->customRule(Scope::REQUEST, ['body']));

        $this->assertCount(1, $rules->rules());
        $this->assertSame(['body'], $rules->invalidatedRequestMatchers());
    }

    public function testAddRejectsACustomIrreversibleBothScopedRuleWithoutOptIn(): void
    {
        $this->expectException(MissingReplacementException::class);

        RedactionRules::create()->add($this->customRule(Scope::BOTH, ['body']));
    }

    public function testAddNeverRejectsACustomIrreversibleResponseScopedRule(): void
    {
        $rules = RedactionRules::create()->add($this->customRule(Scope::RESPONSE));

        $this->assertCount(1, $rules->rules());
    }

    public function testAddNeverRejectsAReversibleRequestScopedRuleWithoutOptIn(): void
    {
        $rules = RedactionRules::create()->add($this->customRule(Scope::REQUEST, [], true));

        $this->assertCount(1, $rules->rules());
    }

    public function testFilterSensitiveDataAddsAValueSubstitutionRule(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('REDACTED', 'secret-value');

        $this->assertInstanceOf(ValueSubstitutionRule::class, $rules->rules()[0]);
    }

    public function testAllHeadersAddsTheWildcardHeaderRule(): void
    {
        $rules = RedactionRules::create()->allHeaders(Scope::RESPONSE);

        $this->assertInstanceOf(HeaderRule::class, $rules->rules()[0]);
        $this->assertSame(Scope::RESPONSE, $rules->rules()[0]->scope());
    }

    public function testQueryParameterAddsAQueryParameterRule(): void
    {
        $rules = RedactionRules::create()->queryParameter('token', 'replacement-query-token');

        $this->assertInstanceOf(QueryParameterRule::class, $rules->rules()[0]);
    }

    public function testPostFieldAddsAPostFieldRule(): void
    {
        $rules = RedactionRules::create()->postField('password', 'replacement-password');

        $this->assertInstanceOf(PostFieldRule::class, $rules->rules()[0]);
    }

    public function testHostAddsAHostRule(): void
    {
        $rules = RedactionRules::create()->host('replacement-host');

        $this->assertInstanceOf(HostRule::class, $rules->rules()[0]);
    }

    public function testBodyAllowsMultipleCallsAddingARuleEach(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->body(static fn (string $body): string => $body, Scope::REQUEST)
            ->body(static fn (string $body): string => $body, Scope::RESPONSE);

        $this->assertCount(2, $rules->rules());
    }

    public function testPostFieldsAllowsMultipleCallsAddingARuleEach(): void
    {
        $rules = RedactionRules::create()
            ->allowIrreversibleRequestRedaction()
            ->postFields(static fn (array $postFields): array => $postFields)
            ->postFields(static fn (array $postFields): array => $postFields);

        $this->assertCount(2, $rules->rules());
    }
}
