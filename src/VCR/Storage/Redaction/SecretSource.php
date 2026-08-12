<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

/**
 * Where a reversible redaction rule gets the real secret from, on demand.
 *
 * The secret itself is never stored on the rule: it is resolved every time it is needed, from
 * either a literal string or a callable, so it can be looked up from an environment variable, a
 * secrets manager, or derived from the recording being processed. A plain string is always the
 * literal secret and is never invoked, so there is no ambiguity with PHP's "string as callable
 * name" convention.
 *
 * This is a boundary: it takes whatever a caller hands over and decides there and then whether it
 * can ever produce a secret. `null` and `false` are accepted rather than rejected, because
 * `getenv()` returns `false` and `$_SERVER['X'] ?? null` returns `null` for an unset variable, and
 * a test suite running without the secret configured should fail with a `MissingSecretException`
 * naming the placeholder instead of a `TypeError` from deep inside this class. Anything else — an
 * int, an array that is not callable, an object without `__invoke()` — is a configuration mistake
 * and is rejected right where it was configured.
 */
final class SecretSource
{
    /**
     * @var string|callable|null the normalised source, with the "not configured" values collapsed into null
     */
    private $source;

    /**
     * Whether the callable source wants the recording passed to it, decided once at construction
     * time via reflection rather than on every resolve().
     *
     * What matters is the *required* parameter count: this keeps both `callable(): ?string` and
     * `callable(array $recording): ?string` sources working without forcing every caller to declare
     * an unused parameter. A no-arg-in-practice callable with an unrelated optional parameter, e.g.
     * `function (string $x = 'foo'): ?string`, must still be called with zero arguments; counting
     * all parameters would pass it a `$recording` array where it expects a `string` and blow up
     * with a `TypeError`.
     */
    private bool $recordingAware = false;

    private string $placeholder;

    /**
     * @param string $placeholder the placeholder this secret is swapped with, named in the exception raised when the secret cannot be resolved
     * @param mixed  $source      a literal secret value, or a callable that resolves one; `null`/`false` stand for "not configured"
     *
     * @throws InvalidRedactionRuleException if $source is neither a string nor a callable, and not
     *                                       one of the `null`/`false` "not configured" values
     */
    public function __construct(string $placeholder, $source)
    {
        $this->placeholder = $placeholder;

        if (\is_string($source)) {
            $this->source = $source;

            return;
        }

        if (null === $source || false === $source) {
            $this->source = null;

            return;
        }

        if (!\is_callable($source)) {
            throw InvalidRedactionRuleException::unsupportedSecretSource($placeholder, get_debug_type($source));
        }

        $this->source = $source;
        $this->recordingAware = (new \ReflectionFunction(\Closure::fromCallable($source)))->getNumberOfRequiredParameters() > 0;
    }

    /**
     * Resolves the secret, evaluating a callable source against the given recording if needed.
     *
     * @param array<string,mixed> $recording the recording a recording-aware source is resolved against
     *
     * @throws MissingSecretException if the source resolves to anything other than a non-empty string
     */
    public function resolve(array $recording): string
    {
        $secret = $this->evaluate($recording);

        if (!\is_string($secret) || '' === $secret) {
            throw MissingSecretException::forPlaceholder($this->placeholder);
        }

        return $secret;
    }

    /**
     * @param array<string,mixed> $recording
     */
    private function evaluate(array $recording): mixed
    {
        $source = $this->source;

        if (!\is_callable($source) || \is_string($source)) {
            return $source;
        }

        return $this->recordingAware ? $source($recording) : $source();
    }
}
