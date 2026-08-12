<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction\Rule;

use VCR\Storage\Redaction\InvalidRedactionRuleException;
use VCR\Storage\Redaction\PlaceholderCollisionException;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\Redaction\SecretSource;

/**
 * Replaces every occurrence of a secret value with a placeholder, anywhere in the recording.
 *
 * Unlike path-based rules, this rule does not target a fixed set of fields: it walks the whole
 * recording and rewrites every string leaf it finds, at any depth. That is the underlying
 * implementation `filterSensitiveData()` composes into a `ValueSubstitutionRule` per configured
 * secret. Because the substitution is a literal, reversible string swap, it never invalidates a
 * request matcher.
 *
 * The secret itself is never stored: {@see SecretSource} resolves it on demand from either a
 * literal string or a callable, so it can be looked up from an environment variable, a secrets
 * manager, or derived from the recording being processed.
 */
final class ValueSubstitutionRule implements RedactionRuleInterface
{
    private string $placeholder;

    private SecretSource $source;

    /**
     * @param string                                                                                   $placeholder the literal text that replaces the secret in the recording
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source      a literal secret value, or a callable that resolves one; a plain
     *                                                                                                              string is always treated as the literal secret, never invoked, so
     *                                                                                                              there is no ambiguity with PHP's "string as callable name" convention.
     *                                                                                                              `false`/`null`, as `getenv()` returns for an unset variable, are
     *                                                                                                              accepted and surface as a `MissingSecretException` when the secret
     *                                                                                                              is actually needed
     *
     * @throws InvalidRedactionRuleException if the placeholder is empty, or the source is neither a
     *                                       string nor a callable
     */
    public function __construct(string $placeholder, $source)
    {
        if ('' === $placeholder) {
            throw InvalidRedactionRuleException::emptyPlaceholder();
        }

        $this->placeholder = $placeholder;
        $this->source = new SecretSource($placeholder, $source);
    }

    public function scope(): string
    {
        return Scope::BOTH;
    }

    public function isReversible(): bool
    {
        return true;
    }

    public function affectedMatchers(): array
    {
        return [];
    }

    public function redact(array $recording): array
    {
        $secret = $this->source->resolve($recording);
        $collisionPath = $this->findFirstOccurrence($recording, $this->placeholder);

        if (null !== $collisionPath) {
            throw PlaceholderCollisionException::forPlaceholder($this->placeholder, $collisionPath);
        }

        return $this->replaceInRecording($recording, $secret, $this->placeholder);
    }

    public function restore(array $recording): array
    {
        $secret = $this->source->resolve($recording);

        return $this->replaceInRecording($recording, $this->placeholder, $secret);
    }

    /**
     * Finds the dotted path of the first string leaf containing $needle, or null if none do.
     *
     * @param array<string,mixed> $recording
     */
    private function findFirstOccurrence(array $recording, string $needle, string $path = ''): ?string
    {
        foreach ($recording as $key => $value) {
            $currentPath = '' === $path ? (string) $key : $path.'.'.$key;

            if (\is_string($value) && str_contains($value, $needle)) {
                return $currentPath;
            }

            if (\is_array($value)) {
                $found = $this->findFirstOccurrence($value, $needle, $currentPath);

                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Replaces every occurrence of $search with $replace in every string leaf of the recording,
     * recursing into nested maps (e.g. headers) and lists (e.g. post_files) alike.
     *
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    private function replaceInRecording(array $recording, string $search, string $replace): array
    {
        foreach ($recording as $key => $value) {
            if (\is_string($value)) {
                $recording[$key] = str_replace($search, $replace, $value);
            } elseif (\is_array($value)) {
                $recording[$key] = $this->replaceInRecording($value, $search, $replace);
            }
        }

        return $recording;
    }
}
