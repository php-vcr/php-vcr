<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction\Rule;

use VCR\Storage\RecordingPath;
use VCR\Storage\Redaction\InvalidRedactionRuleException;
use VCR\Storage\Redaction\Placeholder;
use VCR\Storage\Redaction\RedactionRuleInterface;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\Redaction\SecretSource;

/**
 * Redacts a single header, matched case-insensitively, or every header in scope via {@see self::all()}.
 *
 * With a replacement source, the source *is* the real header value — typically read from an
 * environment variable or a secrets manager. `redact()` writes a placeholder in its place, so the
 * cassette on disk never carries the secret, and `restore()` resolves the source again and writes it
 * back before the request matchers run. Replay therefore compares the real value against the real
 * value a live request carries, with the `headers` matcher left enabled. The source has to resolve
 * to the header's *whole* value, prefix included (`'Bearer '.getenv('API_TOKEN')`, not just the
 * token), because a header rule owns the whole value rather than a substring of it — reach for
 * `filterSensitiveData()` when only part of a value is secret.
 *
 * The wildcard form is blanking-only for that reason: one source cannot stand for every header in
 * scope, so it is rejected at construction rather than writing the same secret into all of them.
 *
 * Without a replacement source, the header's value is blanked to an empty string rather than the
 * header being removed — `RecordingPath` has no key-deletion primitive, only in-place value
 * replacement. This is legal unconditionally on the response side (nothing there is ever matched),
 * but only legal on the request side once the owning `RedactionRules` has been told to accept the
 * resulting matcher gap — this class only reports that gap honestly through
 * `isReversible()`/`affectedMatchers()`, it does not enforce it.
 *
 * Known limitation: blanking is not removal. Code that checks header presence with
 * `array_key_exists()`/`isset()` rather than truthiness (e.g. `Response::getHeader()` returning `''`
 * instead of `null`) will observe the header as present-but-empty after redaction, not absent, on
 * replay. There is currently no way to make a blanked header disappear entirely.
 */
final class HeaderRule implements RedactionRuleInterface
{
    private const WILDCARD = '*';

    private const CONTAINERS = [Scope::REQUEST, Scope::RESPONSE];

    private string $headerName;

    private ?SecretSource $source;

    private string $placeholder;

    /**
     * @var Scope::REQUEST|Scope::RESPONSE|Scope::BOTH
     */
    private string $scope;

    /**
     * @param string                                                                                   $headerName the header to redact, or `'*'` to redact every header in scope
     * @param string|callable(): (string|null)|callable(array<string,mixed>): (string|null)|false|null $source     a literal replacement value, a callable that resolves
     *                                                                                                             one, or null to blank the header instead; rejected
     *                                                                                                             outright for `'*'`, which names more than one header
     * @param string                                                                                   $scope      one of {@see Scope}'s three constants, rejected at construction if it is not
     *
     * @throws InvalidRedactionRuleException if $scope is not a known scope, if $source is neither a
     *                                       string nor a callable, or if $source is given alongside
     *                                       the wildcard header name
     */
    public function __construct(string $headerName, $source = null, string $scope = Scope::BOTH)
    {
        Scope::assertValid($scope);

        // A single source stands for a single header's whole value. Spread across every header in
        // scope it would write the same secret into all of them and restore that one value into all
        // of them on read, quietly rewriting headers that were never sensitive to begin with.
        if (self::WILDCARD === $headerName && null !== $source) {
            throw InvalidRedactionRuleException::wildcardHeaderWithSource(self::WILDCARD);
        }

        $this->headerName = $headerName;
        $this->scope = $scope;
        $this->placeholder = Placeholder::forHeader($headerName);
        $this->source = null === $source ? null : new SecretSource($this->placeholder, $source);
    }

    /**
     * Builds a rule that redacts every header found within the given scope.
     *
     * @param string $scope one of {@see Scope}'s three constants
     */
    public static function all(string $scope): self
    {
        return new self(self::WILDCARD, null, $scope);
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function isReversible(): bool
    {
        return Scope::RESPONSE === $this->scope || null !== $this->source;
    }

    public function affectedMatchers(): array
    {
        return $this->isReversible() ? [] : ['headers'];
    }

    public function redact(array $recording): array
    {
        $paths = $this->matchingSegments($recording);
        $source = $this->source;

        if ([] === $paths) {
            return $recording;
        }

        if (null === $source) {
            return $this->writeAll($recording, $paths, static fn (): string => '');
        }

        // Resolving the secret here without using it is the point: a cassette redacted against a
        // source that cannot be resolved could never be restored, and that failure belongs at
        // record time rather than at the next replay, when the original value is long gone.
        $source->resolve($recording);

        return $this->writeAll($recording, $paths, function ($value, array $segments): string {
            Placeholder::assertAbsent($this->placeholder, $value, implode('.', $segments));

            return $this->placeholder;
        });
    }

    public function restore(array $recording): array
    {
        $paths = $this->matchingSegments($recording);
        $source = $this->source;

        if (null === $source || [] === $paths) {
            return $recording;
        }

        $secret = $source->resolve($recording);

        return $this->writeAll($recording, $paths, static fn (): string => $secret);
    }

    /**
     * @param array<string,mixed>               $recording
     * @param array<int, string[]>              $paths
     * @param \Closure(mixed, string[]): string $replacement receives the current value and the segment path it sits at
     *
     * @return array<string,mixed>
     */
    private function writeAll(array $recording, array $paths, \Closure $replacement): array
    {
        foreach ($paths as $segments) {
            $recording = RecordingPath::replace(
                $recording,
                $segments,
                static fn ($value): string => $replacement($value, $segments)
            );
        }

        return $recording;
    }

    /**
     * @param array<string,mixed> $recording
     *
     * @return array<int, string[]>
     */
    private function matchingSegments(array $recording): array
    {
        $headerNames = self::WILDCARD === $this->headerName
            ? $this->headerNamesInScope($recording)
            : [$this->headerName];

        $segments = RecordingPath::resolvePaths($recording, [], $headerNames);

        return array_values(array_filter(
            $segments,
            fn (array $segment): bool => Scope::includes($this->scope, $segment[0])
        ));
    }

    /**
     * @param array<string,mixed> $recording
     *
     * @return string[]
     */
    private function headerNamesInScope(array $recording): array
    {
        $names = [];

        foreach (self::CONTAINERS as $container) {
            if (!Scope::includes($this->scope, $container)) {
                continue;
            }

            $headers = $recording[$container]['headers'] ?? null;

            if (\is_array($headers)) {
                foreach (array_keys($headers) as $name) {
                    $names[] = (string) $name;
                }
            }
        }

        return $names;
    }
}
