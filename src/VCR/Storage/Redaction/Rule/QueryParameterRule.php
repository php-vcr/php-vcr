<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction\Rule;

use VCR\Storage\Redaction\Placeholder;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\Redaction\SecretSource;
use VCR\Storage\Redaction\UrlParts;

/**
 * Redacts a single named parameter out of the query string carried in `request.url`.
 *
 * There is no query setter on `Request` — the URL string is the only carrier of the query string — so
 * this rule takes the URL apart with `parse_url()`, rewrites the named parameter within the raw query
 * string, and puts the URL back together, leaving every other parameter byte-identical (see
 * {@see UrlParts::rewriteQueryParameter()} for why that is done on the raw segments rather than
 * through `parse_str()`). Query strings only ever exist in the request, so this rule is always
 * request-scoped.
 *
 * With a replacement source, the source *is* the real parameter value: `redact()` writes a
 * placeholder in its place so the cassette never carries it, and `restore()` resolves the source
 * again and writes it back before the request matchers run, so the `query_string` matcher keeps
 * comparing the real value against the real value a live request sends.
 *
 * The source is written into the query string exactly as it resolves, with no percent-encoding
 * applied — supply it in the form the HTTP client actually puts on the wire (`'a%20b'` if the
 * client encodes the space, `'a b'` if it does not). Encoding it here instead would mean picking
 * one convention for every client: `RequestMatcher::matchQueryString()` compares the raw query
 * string byte-for-byte, and `urlencode()` escaping an `@` or writing a space as `+` is enough to
 * fail that comparison against a client that does neither. This is the same contract
 * {@see ValueSubstitutionRule} places on its secret, and the reason both rules can promise a
 * byte-identical round trip at all.
 *
 * The placeholder goes in unencoded for the same reason it can: `<<REDACTED:QUERY_PARAMETER:name>>`
 * carries no `&`, `=` or `#`, so `parse_url()` still hands back the query string whole, and the
 * placeholder is never located by parsing it out — `restore()` rewrites the same named segment
 * this rule wrote, so the value never travels through a URL parser outside these two methods.
 *
 * Without a source, the parameter's value is blanked to an empty string rather than the parameter
 * being removed from the query string — this keeps the URL structurally valid and does not risk the
 * unparseable-URL failure mode `HostRule` avoids by using a placeholder instead of an empty host.
 */
final class QueryParameterRule implements RedactionRuleInterface
{
    private const PATH = 'request.url';

    private string $parameterName;

    private ?SecretSource $source;

    private string $placeholder;

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source a literal replacement value, a callable that resolves
     *                                                                                                         one, or null to blank the parameter instead
     */
    public function __construct(string $parameterName, $source = null)
    {
        $this->parameterName = $parameterName;
        $this->placeholder = Placeholder::forQueryParameter($parameterName);
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
        // Note: the query string is compared by the 'query_string' matcher
        // (RequestMatcher::matchQueryString()), not 'url' — RequestMatcher::matchUrl() only
        // compares the URL path (PHP_URL_PATH), so it is never affected by this rule.
        return $this->isReversible() ? [] : ['query_string'];
    }

    public function redact(array $recording): array
    {
        $source = $this->source;

        if (null === $source) {
            return $this->rewriteParameter($recording, static fn (): string => '');
        }

        return $this->rewriteParameter($recording, function (string $value) use ($source, $recording): string {
            // Resolving the secret here without using it is the point: a cassette redacted against
            // a source that cannot be resolved could never be restored, and that failure belongs at
            // record time rather than at the next replay, when the original value is long gone.
            $source->resolve($recording);

            Placeholder::assertAbsent($this->placeholder, $value, self::PATH);

            return $this->placeholder;
        });
    }

    public function restore(array $recording): array
    {
        $source = $this->source;

        if (null === $source) {
            return $recording;
        }

        return $this->rewriteParameter($recording, static fn (): string => $source->resolve($recording));
    }

    /**
     * Rewrites the named query parameter, leaving the rest of the URL exactly as it was.
     *
     * A no-op if the URL is missing, unparseable, carries no query string, or does not carry the
     * named parameter — in none of those cases is there anything for this rule to redact.
     *
     * @param array<string,mixed>      $recording
     * @param \Closure(string): string $replacement receives the parameter's current, decoded value
     *
     * @return array<string,mixed>
     */
    private function rewriteParameter(array $recording, \Closure $replacement): array
    {
        $url = $recording['request']['url'] ?? null;

        if (!\is_string($url) || '' === $url) {
            return $recording;
        }

        $parts = UrlParts::parse($url);

        if (null === $parts || !isset($parts['query'])) {
            return $recording;
        }

        $query = UrlParts::rewriteQueryParameter((string) $parts['query'], $this->parameterName, $replacement);

        if (null === $query) {
            return $recording;
        }

        $parts['query'] = $query;
        $recording['request']['url'] = UrlParts::toUrl($parts);

        return $recording;
    }
}
