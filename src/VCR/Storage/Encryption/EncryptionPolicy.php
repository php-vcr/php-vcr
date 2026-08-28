<?php

declare(strict_types=1);

namespace VCR\Storage\Encryption;

/**
 * Decides which parts of a recording are encrypted and walks the recording array accordingly.
 *
 * Method, URL and status stay readable on purpose — otherwise a cassette diff carries no information
 * at all.
 */
class EncryptionPolicy
{
    public const DEFAULT_FIELD_PATHS = [
        'request.body',
        'request.post_fields',
        'request.post_files',
        'response.body',
        'response.curl_info.request_header',
    ];

    public const DEFAULT_HEADER_NAMES = [
        'Authorization',
        'Proxy-Authorization',
        'Cookie',
        'Set-Cookie',
        'X-Api-Key',
    ];

    private const TAG_STRING = 's:';

    private const TAG_JSON = 'j:';

    private const HEADER_CONTAINERS = ['request', 'response'];

    /**
     * @var string[]
     */
    private array $fieldPaths;

    /**
     * @var string[]
     */
    private array $headerNames;

    /**
     * @param string[]|null $fieldPaths  dot paths, defaults to self::DEFAULT_FIELD_PATHS
     * @param string[]|null $headerNames matched case-insensitively in request and response headers
     */
    public function __construct(?array $fieldPaths = null, ?array $headerNames = null)
    {
        $this->fieldPaths = $fieldPaths ?? self::DEFAULT_FIELD_PATHS;
        $this->headerNames = array_map('strtolower', $headerNames ?? self::DEFAULT_HEADER_NAMES);
    }

    /**
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    public function encrypt(array $recording, CipherInterface $cipher): array
    {
        foreach ($this->resolvePaths($recording) as $segments) {
            $path = implode('.', $segments);
            $recording = $this->replace($recording, $segments, fn ($value) => $cipher->encrypt($this->tag($value, $path), $path));
        }

        return $recording;
    }

    /**
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    public function decrypt(array $recording, CipherInterface $cipher): array
    {
        foreach ($this->resolvePaths($recording) as $segments) {
            $path = implode('.', $segments);
            $recording = $this->replace($recording, $segments, function ($value) use ($cipher, $path) {
                if (!\is_string($value) || !$cipher->isEncrypted($value)) {
                    return $value;
                }

                return $this->untag($cipher->decrypt($value, $path), $path);
            });
        }

        return $recording;
    }

    /**
     * Resolves every field this policy covers for the given recording into its segment path.
     *
     * Header names are carried through as a single opaque segment rather than being dot-joined,
     * since HTTP header names are themselves allowed to contain literal dots (e.g. "X.Api.Secret")
     * and re-splitting a joined string on "." would misinterpret such a name as several segments.
     *
     * @param array<string,mixed> $recording
     *
     * @return array<int, string[]>
     */
    private function resolvePaths(array $recording): array
    {
        $paths = array_map(static fn (string $fieldPath): array => explode('.', $fieldPath), $this->fieldPaths);

        foreach (self::HEADER_CONTAINERS as $container) {
            $headers = $recording[$container]['headers'] ?? null;

            if (!\is_array($headers)) {
                continue;
            }

            foreach (array_keys($headers) as $name) {
                if (\in_array(strtolower((string) $name), $this->headerNames, true)) {
                    $paths[] = [$container, 'headers', (string) $name];
                }
            }
        }

        return $paths;
    }

    /**
     * @param array<string,mixed>    $recording
     * @param string[]               $segments
     * @param \Closure(mixed): mixed $transform
     *
     * @return array<string,mixed>
     */
    private function replace(array $recording, array $segments, \Closure $transform): array
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

    private function tag(mixed $value, string $path): string
    {
        if (\is_string($value)) {
            return self::TAG_STRING.$value;
        }

        $encoded = json_encode($value);

        if (!\is_string($encoded)) {
            throw new \RuntimeException(\sprintf('The value in "%s" cannot be encoded for encryption.', $path));
        }

        return self::TAG_JSON.$encoded;
    }

    private function untag(string $plaintext, string $path): mixed
    {
        $tag = substr($plaintext, 0, 2);
        $payload = substr($plaintext, 2);

        if (self::TAG_STRING === $tag) {
            return $payload;
        }

        if (self::TAG_JSON === $tag) {
            return json_decode($payload, true);
        }

        throw DecryptionFailedException::malformed($path);
    }
}
