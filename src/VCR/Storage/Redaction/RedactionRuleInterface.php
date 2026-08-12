<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

/**
 * A single, declarative rule that strips or masks sensitive data from a recorded cassette entry.
 *
 * Rules are composed by `RedactionRules` and applied to every recording written to (or read from)
 * a cassette. A rule that is reversible can undo its own transformation on replay, so the original
 * value is available again to the code under test; an irreversible rule permanently discards the
 * original value and instead invalidates the request matchers that relied on it.
 */
interface RedactionRuleInterface
{
    /**
     * The side(s) of the recorded interaction this rule inspects and rewrites.
     *
     * @return Scope::REQUEST|Scope::RESPONSE|Scope::BOTH
     */
    public function scope(): string;

    /**
     * Whether this rule can restore the original value on replay.
     *
     * A reversible rule's redaction can be undone by restore(); an irreversible rule permanently
     * discards the original value.
     */
    public function isReversible(): bool;

    /**
     * Matcher keys this rule invalidates when it is not reversible.
     *
     * Only meaningful when isReversible() is false: since the original value is gone for good,
     * any request matcher that would compare against it (e.g. "body", "post_fields") can no longer
     * be relied on and must be excluded from matching.
     *
     * @return list<string> matcher keys, from the set of matchers Configuration knows about
     */
    public function affectedMatchers(): array;

    /**
     * Applies the redaction to a recording before it is written to the cassette.
     *
     * @param array<string,mixed> $recording the recording as it would otherwise be written
     *
     * @return array<string,mixed> the recording with this rule's redaction applied
     */
    public function redact(array $recording): array;

    /**
     * Reverses the redaction on a recording read back from the cassette.
     *
     * For a reversible rule, this restores the original value that redact() replaced. For an
     * irreversible rule, this is a no-op: the recording is returned unchanged, since the original
     * value was never retained.
     *
     * @param array<string,mixed> $recording the recording as read from the cassette
     *
     * @return array<string,mixed> the recording with this rule's redaction reversed
     */
    public function restore(array $recording): array;
}
