<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

/**
 * Takes a URL apart into `parse_url()` components and puts it back together again.
 *
 * `parse_url()` has no counterpart in PHP's standard library, so a rule that rewrites one component
 * of `request.url` — the host, a single query parameter — has to reassemble the rest by hand. Doing
 * that in one place keeps `HostRule` and `QueryParameterRule` from drifting apart on which
 * components they preserve.
 */
final class UrlParts
{
    private function __construct()
    {
    }

    /**
     * Splits a URL into its components.
     *
     * @param string $url the URL to split
     *
     * @return array<string,int|string>|null the components found, or null if the URL is unparseable
     */
    public static function parse(string $url): ?array
    {
        $parts = parse_url($url);

        return false === $parts ? null : $parts;
    }

    /**
     * Rewrites one named parameter inside a raw query string, leaving every other one byte-identical.
     *
     * Deliberately not built on `parse_str()`/`http_build_query()`: `parse_str()` mangles keys the
     * way PHP mangles variable names, turning a dot or a space into an underscore. Round-tripping a
     * query string through that pair rewrites parameters this rule never targeted (`client.secret`
     * comes back as `client_secret`), which breaks the `query_string` matcher on replay, and makes a
     * parameter whose own name carries a dot impossible to find — so its value would silently stay
     * on the cassette in plaintext. Working on the raw segments avoids both.
     *
     * Every occurrence of the parameter is rewritten, not just the last one, so a repeated
     * parameter cannot leave one copy of the secret behind.
     *
     * The string the replacement returns is written into the query string **verbatim**, with no
     * percent-encoding applied to it. There is no encoding convention every HTTP client follows
     * identically — `urlencode()` escapes `@` and writes a space as `+`, `rawurlencode()` writes
     * `%20`, and a given client may leave either untouched — so re-encoding the value here cannot
     * reproduce byte-for-byte what a live request will carry, and `RequestMatcher::matchQueryString()`
     * compares the raw query string byte-for-byte. Writing the value exactly as given puts that
     * choice where the knowledge is, with the caller, the same contract
     * {@see Rule\ValueSubstitutionRule} has always had for its secret.
     *
     * Reading is deliberately not symmetric: the parameter's name and its current value are both
     * compared *decoded*, so a percent-encoded name is still found and a percent-encoded secret is
     * still recognised by the collision guard.
     *
     * @param string                   $query       the raw query string, without the leading "?"
     * @param string                   $name        the parameter to rewrite, compared decoded
     * @param \Closure(string): string $replacement receives the parameter's current, decoded value,
     *                                              and returns the replacement to write verbatim
     *
     * @return string|null the rewritten query string, or null if the parameter does not occur in it
     */
    public static function rewriteQueryParameter(string $query, string $name, \Closure $replacement): ?string
    {
        $segments = explode('&', $query);
        $found = false;

        foreach ($segments as $index => $segment) {
            $separator = strpos($segment, '=');
            $rawName = false === $separator ? $segment : substr($segment, 0, $separator);

            if (urldecode($rawName) !== $name) {
                continue;
            }

            $currentValue = false === $separator ? '' : urldecode(substr($segment, $separator + 1));
            $segments[$index] = $rawName.'='.$replacement($currentValue);
            $found = true;
        }

        return $found ? implode('&', $segments) : null;
    }

    /**
     * Reassembles a URL from the components `parse_url()` produced, preserving every one of them.
     *
     * @param array<string,int|string> $parts the components to reassemble, as returned by {@see self::parse()}
     */
    public static function toUrl(array $parts): string
    {
        $url = '';

        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'].'://';
        }

        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':'.$parts['pass'];
            }
            $url .= '@';
        }

        $url .= $parts['host'] ?? '';

        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }

        $url .= $parts['path'] ?? '';

        if (isset($parts['query']) && '' !== $parts['query']) {
            $url .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }
}
