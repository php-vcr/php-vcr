<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

/**
 * A redaction rule was configured with an argument it can never work with.
 *
 * Unlike `MissingSecretException`, which reports a secret that was well-configured but happened to
 * resolve to nothing at record time, this signals a programming error at composition time: an empty
 * placeholder, a secret source that is neither a string nor a callable, a scope string that is not
 * one of `Scope`'s three values, or a replacement source handed to a rule that targets more than
 * one field. Each of those would otherwise turn into a silent no-op, a corrupted cassette, or a raw
 * `TypeError` far away from the line that configured the rule.
 */
class InvalidRedactionRuleException extends \InvalidArgumentException
{
    public static function emptyPlaceholder(): self
    {
        return new self('A redaction placeholder must not be empty, otherwise the redacted value cannot be told apart from the surrounding text.');
    }

    public static function unsupportedSecretSource(string $placeholder, string $givenType): self
    {
        return new self(\sprintf(
            'The replacement source for placeholder "%s" must be a string or a callable, %s given.',
            $placeholder,
            $givenType
        ));
    }

    public static function wildcardHeaderWithSource(string $wildcard): self
    {
        return new self(\sprintf(
            'A header rule matching "%s" cannot take a replacement source: it would write that one value into every header in scope and restore it into all of them, so a recording with more than one header comes back corrupted. Name the header the source belongs to, or drop the source to blank every header instead.',
            $wildcard
        ));
    }

    public static function unsupportedScope(string $scope): self
    {
        return new self(\sprintf(
            'The scope "%s" is not one of "%s", "%s" or "%s".',
            $scope,
            Scope::REQUEST,
            Scope::RESPONSE,
            Scope::BOTH
        ));
    }
}
