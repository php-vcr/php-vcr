<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

use VCR\Storage\Redaction\Rule\BodyCallbackRule;
use VCR\Storage\Redaction\Rule\HeaderRule;
use VCR\Storage\Redaction\Rule\HostRule;
use VCR\Storage\Redaction\Rule\PostFieldRule;
use VCR\Storage\Redaction\Rule\PostFieldsCallbackRule;
use VCR\Storage\Redaction\Rule\QueryParameterRule;
use VCR\Storage\Redaction\Rule\ValueSubstitutionRule;

/**
 * Fluent collection of redaction rules, and the single place their reversibility contract is
 * enforced.
 *
 * This is the composition root every consumer touches directly: `RedactionRules::create()`
 * followed by a chain of convenience methods (`header()`, `queryParameter()`, ...) or `add()` for
 * a custom {@see RedactionRuleInterface} implementation. Every rule is checked at composition
 * time, not at record time, so a configuration mistake surfaces immediately rather than silently
 * corrupting a cassette: an irreversible rule that touches the request side would, on replay,
 * leave the request matchers comparing against data that no longer matches what a live request
 * carries. `allowIrreversibleRequestRedaction()` is the explicit escape hatch for callers who
 * accept that risk and have narrowed their request matchers accordingly (see
 * `safeRequestMatchers()`/`invalidatedRequestMatchers()`).
 */
final class RedactionRules
{
    /**
     * Mirrors `Configuration::$availableRequestMatchers`' keys, in the same order. That property
     * is private, so it cannot be referenced directly; this is the closest thing to a shared
     * source of truth available without changing `Configuration`'s visibility.
     *
     * @var list<string>
     */
    private const DEFAULT_REQUEST_MATCHERS = [
        'method',
        'url',
        'host',
        'headers',
        'body',
        'post_fields',
        'query_string',
        'soap_operation',
    ];

    /**
     * @var list<RedactionRuleInterface>
     */
    private array $rules = [];

    private bool $irreversibleRequestRedactionAllowed = false;

    public static function create(): self
    {
        return new self();
    }

    /**
     * Registers a redaction rule, enforcing the reversibility contract.
     *
     * A rule that cannot restore its own redaction and whose scope includes the request side is
     * rejected unless {@see self::allowIrreversibleRequestRedaction()} has already been called:
     * such a rule would permanently invalidate part of the request the configured matchers rely
     * on, breaking replay. A response-only rule is never rejected, reversible or not, because the
     * response is never matched against.
     *
     * @throws MissingReplacementException if the rule is irreversible, request-scoped, and the
     *                                     opt-in has not been given
     */
    public function add(RedactionRuleInterface $rule): self
    {
        $scopeIncludesRequest = Scope::includes($rule->scope(), Scope::REQUEST);

        if (!$rule->isReversible() && $scopeIncludesRequest && !$this->irreversibleRequestRedactionAllowed) {
            throw MissingReplacementException::forRule($rule::class);
        }

        $this->rules[] = $rule;

        return $this;
    }

    /**
     * Opts into registering irreversible, request-scoped rules.
     *
     * Once called, {@see self::add()} no longer rejects such rules. Callers are expected to have
     * accounted for the resulting matcher gap themselves, e.g. via {@see self::safeRequestMatchers()}.
     */
    public function allowIrreversibleRequestRedaction(): self
    {
        $this->irreversibleRequestRedactionAllowed = true;

        return $this;
    }

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source
     */
    public function filterSensitiveData(string $placeholder, $source): self
    {
        return $this->add(new ValueSubstitutionRule($placeholder, $source));
    }

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source
     * @param Scope::REQUEST|Scope::RESPONSE|Scope::BOTH                                               $scope
     */
    public function header(string $headerName, $source = null, string $scope = Scope::BOTH): self
    {
        return $this->add(new HeaderRule($headerName, $source, $scope));
    }

    /**
     * @param Scope::REQUEST|Scope::RESPONSE|Scope::BOTH $scope
     */
    public function allHeaders(string $scope): self
    {
        return $this->add(HeaderRule::all($scope));
    }

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source
     */
    public function queryParameter(string $parameterName, $source = null): self
    {
        return $this->add(new QueryParameterRule($parameterName, $source));
    }

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source
     */
    public function postField(string $fieldName, $source = null): self
    {
        return $this->add(new PostFieldRule($fieldName, $source));
    }

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source
     */
    public function host($source = null): self
    {
        return $this->add(new HostRule($source));
    }

    /**
     * @param callable(string): string                   $callback
     * @param Scope::REQUEST|Scope::RESPONSE|Scope::BOTH $scope
     */
    public function body(callable $callback, string $scope): self
    {
        return $this->add(new BodyCallbackRule($callback, $scope));
    }

    /**
     * @param callable(array<string,mixed>): array<string,mixed> $callback
     */
    public function postFields(callable $callback): self
    {
        return $this->add(new PostFieldsCallbackRule($callback));
    }

    /**
     * The default request matcher keys that remain reliable given the rules added so far.
     *
     * @return list<string>
     */
    public function safeRequestMatchers(): array
    {
        $invalidated = $this->invalidatedRequestMatchers();

        return array_values(array_filter(
            self::DEFAULT_REQUEST_MATCHERS,
            static fn (string $matcher): bool => !\in_array($matcher, $invalidated, true)
        ));
    }

    /**
     * The default request matcher keys invalidated by at least one added rule.
     *
     * @return list<string>
     */
    public function invalidatedRequestMatchers(): array
    {
        $affected = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->affectedMatchers() as $matcher) {
                $affected[$matcher] = true;
            }
        }

        return array_values(array_filter(
            self::DEFAULT_REQUEST_MATCHERS,
            static fn (string $matcher): bool => isset($affected[$matcher])
        ));
    }

    /**
     * @return list<RedactionRuleInterface>
     */
    public function rules(): array
    {
        return $this->rules;
    }
}
