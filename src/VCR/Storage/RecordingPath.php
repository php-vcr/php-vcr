<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Walks a recorded cassette entry (an associative array) along dot-separated field paths and
 * header-name matches, so callers can read or rewrite the values found there.
 *
 * Shared between `EncryptionPolicy` and redaction rules, which both need to resolve which parts
 * of a recording to touch and then either inspect or replace the value found at that location. It
 * therefore lives next to both `Encryption` and `Redaction` rather than inside either of them:
 * nothing here knows about ciphers or redaction rules, only about the array shape a recording has
 * on the wire.
 */
final class RecordingPath
{
    private const HEADER_CONTAINERS = ['request', 'response'];

    private function __construct()
    {
    }

    /**
     * Resolves field paths and header-name matches for the given recording into segment paths.
     *
     * Header names are carried through as a single opaque segment rather than being dot-joined,
     * since HTTP header names are themselves allowed to contain literal dots (e.g. "X.Api.Secret")
     * and re-splitting a joined string on "." would misinterpret such a name as several segments.
     *
     * @param array<string,mixed> $recording   the recording to resolve paths against
     * @param string[]            $fieldPaths  dot paths, e.g. "request.body"
     * @param string[]            $headerNames matched case-insensitively against header names found
     *                                         in the "request" and "response" header containers
     *
     * @return array<int, string[]> one segment array per resolved path
     */
    public static function resolvePaths(array $recording, array $fieldPaths, array $headerNames): array
    {
        $paths = array_map(static fn (string $fieldPath): array => explode('.', $fieldPath), $fieldPaths);
        $headerNames = array_map('strtolower', $headerNames);

        foreach (self::HEADER_CONTAINERS as $container) {
            $headers = $recording[$container]['headers'] ?? null;

            if (!\is_array($headers)) {
                continue;
            }

            foreach (array_keys($headers) as $name) {
                if (\in_array(strtolower((string) $name), $headerNames, true)) {
                    $paths[] = [$container, 'headers', (string) $name];
                }
            }
        }

        return $paths;
    }

    /**
     * Replaces the value at the given segment path with the result of applying $transform to it.
     *
     * Tolerant of missing segments: if any segment along the path does not exist, the recording is
     * returned unchanged rather than raising an error.
     *
     * @param array<string,mixed>    $recording the recording to rewrite
     * @param string[]               $segments  the segment path to replace the value at
     * @param \Closure(mixed): mixed $transform receives the current value, returns the replacement
     *
     * @return array<string,mixed> the recording with the value at $segments replaced
     */
    public static function replace(array $recording, array $segments, \Closure $transform): array
    {
        $lastIndex = \count($segments) - 1;
        $cursor = &$recording;

        foreach ($segments as $depth => $segment) {
            if (!\is_array($cursor) || !\array_key_exists($segment, $cursor)) {
                unset($cursor);

                return $recording;
            }

            if ($depth === $lastIndex) {
                $cursor[$segment] = $transform($cursor[$segment]);
                unset($cursor);

                return $recording;
            }

            $cursor = &$cursor[$segment];
        }

        unset($cursor);

        return $recording;
    }

    /**
     * Reads the value at the given segment path.
     *
     * @param array<string,mixed> $recording the recording to read from
     * @param string[]            $segments  the segment path to read the value at
     *
     * @return mixed the value found at $segments, or null if any segment along the path is missing
     */
    public static function get(array $recording, array $segments): mixed
    {
        $cursor = $recording;

        foreach ($segments as $segment) {
            if (!\is_array($cursor) || !\array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
