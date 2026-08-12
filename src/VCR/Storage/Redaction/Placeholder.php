<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

/**
 * Builds the literal a field-targeted redaction rule writes to the cassette in place of a secret.
 *
 * `filterSensitiveData()` lets the caller pick the placeholder, because the caller is the one who
 * has to recognise it in a cassette diff. The field-targeted rules cannot: their constructors were
 * designed to take just a field name and a source, so the placeholder has to be derived. It is
 * derived from the rule's own identity — never randomised — so that re-recording the same cassette
 * produces byte-identical output instead of a spurious diff, the same reasoning behind the
 * encryption backend's deterministic nonce.
 *
 * The shape is `<<REDACTED:KIND:name>>`: recognisable at a glance in a cassette, and unlikely to
 * the point of absurdity to occur in real payload data. {@see self::assertAbsent()} turns "unlikely"
 * into "checked" — a value that already contains the placeholder before redaction is rejected rather
 * than silently corrupted, since restoring it afterwards would rewrite the caller's own text too.
 */
final class Placeholder
{
    private const FORMAT = '<<REDACTED:%s:%s>>';

    /**
     * The host counterpart of {@see self::FORMAT}.
     *
     * A host placeholder ends up inside `request.url`, which every rule applied afterwards — and
     * `Request::fromArray()` on the next replay — still has to be able to run through `parse_url()`.
     * Angle brackets and colons would make the URL unparseable, so the host placeholder is a
     * hostname instead, in the `.invalid` TLD RFC 2606 reserves precisely so that it can never
     * resolve to a real machine.
     */
    private const HOST = 'redacted-host.invalid';

    private function __construct()
    {
    }

    /**
     * The placeholder written in place of the named header's value.
     *
     * The name is lowercased because headers are matched case-insensitively: `Authorization` and
     * `AUTHORIZATION` name the same header and therefore have to yield the same placeholder.
     */
    public static function forHeader(string $headerName): string
    {
        return \sprintf(self::FORMAT, 'HEADER', strtolower($headerName));
    }

    /**
     * The placeholder written in place of the named post field's value.
     */
    public static function forPostField(string $fieldName): string
    {
        return \sprintf(self::FORMAT, 'POST_FIELD', $fieldName);
    }

    /**
     * The placeholder written in place of the named query parameter's value.
     */
    public static function forQueryParameter(string $parameterName): string
    {
        return \sprintf(self::FORMAT, 'QUERY_PARAMETER', $parameterName);
    }

    /**
     * The placeholder written in place of the request's host — see {@see self::HOST} for why it does
     * not follow the shape the other three share.
     */
    public static function forHost(): string
    {
        return self::HOST;
    }

    /**
     * Rejects a value that already carries the placeholder before it is redacted.
     *
     * @param string $placeholder the placeholder about to be written
     * @param mixed  $value       the value about to be replaced; anything but a string trivially passes
     * @param string $path        the dotted path of $value, named in the exception
     *
     * @throws PlaceholderCollisionException if $value already contains $placeholder
     */
    public static function assertAbsent(string $placeholder, $value, string $path): void
    {
        if (\is_string($value) && str_contains($value, $placeholder)) {
            throw PlaceholderCollisionException::forPlaceholder($placeholder, $path);
        }
    }
}
