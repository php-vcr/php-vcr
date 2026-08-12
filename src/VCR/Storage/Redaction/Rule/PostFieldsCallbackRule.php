<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction\Rule;

use VCR\Storage\RecordingPath;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;

/**
 * Rewrites the whole `request.post_fields` array through an arbitrary user-supplied callback.
 *
 * This is the escape hatch for redaction needs the field- and value-targeted rules cannot
 * express: the callback receives the current post fields verbatim and returns their replacement.
 * Post fields only ever exist on the request side, so this rule is always request-scoped. Because
 * the transformation is arbitrary, it is irreversible by nature: there is no way to recover the
 * original post fields from the redacted ones.
 *
 * Chaining several callbacks over the same post fields is not this class's responsibility.
 * Registering multiple `PostFieldsCallbackRule` instances already produces that behaviour, since
 * `RedactionRules` applies each rule's `redact()` in registration order and each one sees the post
 * fields the previous rule already rewrote.
 */
final class PostFieldsCallbackRule implements RedactionRuleInterface
{
    /**
     * @var callable(array<string,mixed>): array<string,mixed>
     */
    private $callback;

    /**
     * @param callable(array<string,mixed>): array<string,mixed> $callback receives the current post fields, returns their replacement
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function scope(): string
    {
        return Scope::REQUEST;
    }

    public function isReversible(): bool
    {
        return false;
    }

    public function affectedMatchers(): array
    {
        return ['post_fields'];
    }

    public function redact(array $recording): array
    {
        return RecordingPath::replace(
            $recording,
            ['request', 'post_fields'],
            fn (mixed $postFields): array => ($this->callback)(\is_array($postFields) ? $postFields : [])
        );
    }

    /**
     * No-op: the callback's transformation is arbitrary and cannot be inverted, so the original
     * post fields are gone for good once redact() has run.
     *
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    public function restore(array $recording): array
    {
        return $recording;
    }
}
