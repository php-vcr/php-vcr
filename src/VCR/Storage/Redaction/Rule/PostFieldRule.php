<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction\Rule;

use VCR\Storage\RecordingPath;
use VCR\Storage\Redaction\Placeholder;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\Redaction\SecretSource;

/**
 * Redacts a single key inside `request.post_fields`.
 *
 * Post fields only ever exist on the request side, so this rule is always request-scoped. With a
 * replacement source, the source *is* the real field value: `redact()` writes a placeholder in its
 * place so the cassette never carries it, and `restore()` resolves the source again and writes it
 * back before the request matchers run, so the `post_fields` matcher keeps comparing the real value
 * against the real value a live request sends.
 *
 * Without a source, the field's value is blanked to an empty string rather than the key being
 * removed — `RecordingPath` has no key-deletion primitive. Unlike a response header, a post field is
 * never handed back to application code during replay (it only ever feeds request matching), so this
 * does not change replay-observable behaviour the way a blanked response header does.
 */
final class PostFieldRule implements RedactionRuleInterface
{
    private string $fieldName;

    private ?SecretSource $source;

    private string $placeholder;

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source a literal replacement value, a callable that resolves
     *                                                                                                         one, or null to blank the field instead
     */
    public function __construct(string $fieldName, $source = null)
    {
        $this->fieldName = $fieldName;
        $this->placeholder = Placeholder::forPostField($fieldName);
        $this->source = null === $source ? null : new SecretSource($this->placeholder, $source);
    }

    public function scope(): string
    {
        return Scope::REQUEST;
    }

    public function isReversible(): bool
    {
        return null !== $this->source;
    }

    public function affectedMatchers(): array
    {
        return $this->isReversible() ? [] : ['post_fields'];
    }

    public function redact(array $recording): array
    {
        $source = $this->source;

        if (!$this->hasField($recording)) {
            return $recording;
        }

        if (null === $source) {
            return $this->writeField($recording, '');
        }

        // Resolving the secret here without using it is the point: a cassette redacted against a
        // source that cannot be resolved could never be restored, and that failure belongs at
        // record time rather than at the next replay, when the original value is long gone.
        $source->resolve($recording);

        Placeholder::assertAbsent(
            $this->placeholder,
            RecordingPath::get($recording, $this->segments()),
            implode('.', $this->segments())
        );

        return $this->writeField($recording, $this->placeholder);
    }

    public function restore(array $recording): array
    {
        $source = $this->source;

        if (null === $source || !$this->hasField($recording)) {
            return $recording;
        }

        return $this->writeField($recording, $source->resolve($recording));
    }

    /**
     * @param array<string,mixed> $recording
     */
    private function hasField(array $recording): bool
    {
        $postFields = $recording['request']['post_fields'] ?? null;

        return \is_array($postFields) && \array_key_exists($this->fieldName, $postFields);
    }

    /**
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    private function writeField(array $recording, string $value): array
    {
        return RecordingPath::replace($recording, $this->segments(), static fn (): string => $value);
    }

    /**
     * @return string[]
     */
    private function segments(): array
    {
        return ['request', 'post_fields', $this->fieldName];
    }
}
