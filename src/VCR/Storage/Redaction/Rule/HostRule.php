<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction\Rule;

use VCR\Storage\RecordingPath;
use VCR\Storage\Redaction\Placeholder;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\Redaction\SecretSource;
use VCR\Storage\Redaction\UrlParts;

/**
 * Redacts the host consistently in both `request.url` and the `Host` header, if present.
 *
 * The host only ever exists on the request side, so this rule is always request-scoped. With a
 * replacement source, the source *is* the real host: `redact()` writes a placeholder host in its
 * place so the cassette never names the real machine, and `restore()` resolves the source again and
 * writes it back before the request matchers run, so both the `host` and `headers` matchers keep
 * comparing the real host against the real host a live request carries. The source resolves to the
 * bare host, without a port — the URL's port component is not this rule's to rewrite, and is left
 * exactly where it was — and is written into both locations verbatim, never percent-encoded, for
 * the same reason {@see QueryParameterRule} writes its value raw: the matchers compare what is on
 * the wire, so the caller supplies the value in the form the wire carries it.
 *
 * The `Host` header is rewritten by swapping the old host out of its value rather than overwriting
 * it, so an appended `:8443` survives the round trip. The host is located case-insensitively, so a
 * header that spells it differently from the URL still keeps everything around it; a header value
 * that does not carry the host at all is overwritten wholesale instead, so the real host cannot
 * survive in it either way.
 *
 * Without a source, the host is set to a fixed, non-empty value rather than blanked to an empty
 * string. An empty host makes the URL unparseable (`parse_url()` returns `false` for a URL like
 * `https://:8443/path`), which breaks `Request::fromArray()` on the next replay (it calls
 * `getHost()` to backfill a missing `Host` header, which throws `InvalidHostException` once the URL
 * can no longer be parsed) and can silently defeat *other* rules applied to the same recording: a
 * `QueryParameterRule` running after an empty-host `HostRule` would see `parse_url()` fail and no-op
 * instead of redacting its own target. The same constraint is why the reversible placeholder is a
 * hostname rather than the `<<REDACTED:...>>` shape the other rules use — see {@see Placeholder}.
 */
final class HostRule implements RedactionRuleInterface
{
    private const BLANKED_HOST = 'redacted';

    private const PATH = 'request.url';

    private ?SecretSource $source;

    private string $placeholder;

    /**
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source a literal replacement host, a callable that resolves
     *                                                                                                         one, or null to redact to a fixed placeholder instead
     */
    public function __construct($source = null)
    {
        $this->placeholder = Placeholder::forHost();
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
        // Note: 'url' is deliberately not reported here — RequestMatcher::matchUrl() only
        // compares the URL path (PHP_URL_PATH), never the host, so this rule cannot invalidate
        // it. 'headers' is reported instead of 'url': this rule keeps the Host header in sync
        // with the URL's host component, and RequestMatcher::matchHeaders() does a strict
        // whole-array comparison that a rewritten Host header would fail without a source.
        return $this->isReversible() ? [] : ['host', 'headers'];
    }

    public function redact(array $recording): array
    {
        $source = $this->source;

        if (null === $source) {
            return $this->rewriteHost(
                $recording,
                static fn (): string => self::BLANKED_HOST,
                static fn (): string => self::BLANKED_HOST
            );
        }

        return $this->rewriteHost(
            $recording,
            function ($currentHost) use ($source, $recording): string {
                // Resolving the secret here without using it is the point: a cassette redacted
                // against a source that cannot be resolved could never be restored, and that
                // failure belongs at record time rather than at the next replay, when the original
                // value is long gone.
                $source->resolve($recording);

                Placeholder::assertAbsent($this->placeholder, $currentHost, self::PATH);

                return $this->placeholder;
            },
            fn ($value, string $previousHost): string => $this->swapHostInHeaderValue($value, $previousHost, $this->placeholder)
        );
    }

    public function restore(array $recording): array
    {
        $source = $this->source;

        if (null === $source) {
            return $recording;
        }

        return $this->rewriteHost(
            $recording,
            static fn (): string => $source->resolve($recording),
            fn ($value, string $previousHost): string => $this->swapHostInHeaderValue($value, $previousHost, $source->resolve($recording))
        );
    }

    /**
     * Rewrites the host in `request.url` and mirrors it into the request's `Host` header.
     *
     * A no-op if the URL is missing or unparseable — there is no host to redact in either case.
     *
     * @param array<string,mixed>             $recording
     * @param \Closure(string): string        $resolveHost receives the host currently in the URL, returns its replacement
     * @param \Closure(mixed, string): string $headerValue receives the Host header's current value and the host being replaced
     *
     * @return array<string,mixed>
     */
    private function rewriteHost(array $recording, \Closure $resolveHost, \Closure $headerValue): array
    {
        $url = $recording['request']['url'] ?? null;

        if (!\is_string($url) || '' === $url) {
            return $recording;
        }

        $parts = UrlParts::parse($url);

        if (null === $parts) {
            return $recording;
        }

        $previousHost = (string) ($parts['host'] ?? '');
        $parts['host'] = $resolveHost($previousHost);
        $recording['request']['url'] = UrlParts::toUrl($parts);

        foreach (RecordingPath::resolvePaths($recording, [], ['Host']) as $segments) {
            if (Scope::REQUEST !== $segments[0]) {
                continue;
            }

            $recording = RecordingPath::replace(
                $recording,
                $segments,
                static fn ($value): string => $headerValue($value, $previousHost)
            );
        }

        return $recording;
    }

    /**
     * Swaps the host out of the `Host` header's value, leaving everything else it carries in place.
     *
     * The host is located case-insensitively, because hostnames are case-insensitive per RFC 3986
     * and a client is free to send `api.example.com` for a URL written as `API.Example.com`. A
     * byte-exact search would miss that value, leaving only the wholesale overwrite below — which
     * throws away whatever else the header carried, typically the port, so the `headers` matcher
     * then fails on replay even though nothing leaked. The host itself comes back in the
     * replacement's casing rather than the header's own, which the header cannot preserve: a single
     * source stands for both locations, and the casing the header used is not on the cassette.
     *
     * A value that does not name the host at all is still overwritten wholesale, so the real host
     * cannot survive in it either way.
     *
     * @param mixed $value the Host header's current value
     */
    private function swapHostInHeaderValue($value, string $previousHost, string $host): string
    {
        if (!\is_string($value) || '' === $previousHost) {
            return $host;
        }

        $occurrences = 0;
        $swapped = str_ireplace($previousHost, $host, $value, $occurrences);

        return 0 === $occurrences ? $host : $swapped;
    }
}
