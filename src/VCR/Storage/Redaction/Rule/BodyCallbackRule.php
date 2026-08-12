<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction\Rule;

use VCR\Storage\RecordingPath;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;

/**
 * Rewrites the request and/or response body through an arbitrary user-supplied callback.
 *
 * This is the escape hatch for redaction needs the other rules cannot express: the callback
 * receives the current body verbatim and returns its replacement, with no assumption about
 * format (JSON, XML, form-encoded, ...). Because the transformation is arbitrary, it is
 * irreversible by nature: there is no way to recover the original body from the redacted one.
 *
 * Chaining several callbacks over the same body is not this class's responsibility. Registering
 * multiple `BodyCallbackRule` instances already produces that behaviour, since `RedactionRules`
 * applies each rule's `redact()` in registration order and each one sees the body the previous
 * rule already rewrote.
 */
final class BodyCallbackRule implements RedactionRuleInterface
{
    /**
     * @var callable(string): string
     */
    private $callback;

    /**
     * @var Scope::REQUEST|Scope::RESPONSE|Scope::BOTH
     */
    private string $scope;

    /**
     * @param callable(string): string $callback receives the current body, returns its replacement
     * @param string                   $scope    which side(s) of the interaction to apply the callback to,
     *                                           one of {@see Scope}'s three constants
     *
     * @throws \VCR\Storage\Redaction\InvalidRedactionRuleException if $scope is not a known scope
     */
    public function __construct(callable $callback, string $scope)
    {
        Scope::assertValid($scope);

        $this->callback = $callback;
        $this->scope = $scope;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function affectedMatchers(): array
    {
        return Scope::RESPONSE === $this->scope ? [] : ['body'];
    }

    public function redact(array $recording): array
    {
        if (Scope::includes($this->scope, Scope::REQUEST)) {
            $recording = $this->applyCallback($recording, Scope::REQUEST);
        }

        if (Scope::includes($this->scope, Scope::RESPONSE)) {
            $recording = $this->applyCallback($recording, Scope::RESPONSE);
        }

        return $recording;
    }

    /**
     * No-op: the callback's transformation is arbitrary and cannot be inverted, so the original
     * body is gone for good once redact() has run.
     *
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    public function restore(array $recording): array
    {
        return $recording;
    }

    /**
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    private function applyCallback(array $recording, string $container): array
    {
        return RecordingPath::replace(
            $recording,
            [$container, 'body'],
            fn (mixed $body): string => ($this->callback)(\is_string($body) ? $body : '')
        );
    }
}
