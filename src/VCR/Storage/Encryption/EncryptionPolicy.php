<?php

declare(strict_types=1);

namespace VCR\Storage\Encryption;

use VCR\Storage\RecordingPath;

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
        $this->headerNames = $headerNames ?? self::DEFAULT_HEADER_NAMES;
    }

    /**
     * @param array<string,mixed> $recording
     *
     * @return array<string,mixed>
     */
    public function encrypt(array $recording, CipherInterface $cipher): array
    {
        foreach (RecordingPath::resolvePaths($recording, $this->fieldPaths, $this->headerNames) as $segments) {
            $path = implode('.', $segments);
            $recording = RecordingPath::replace($recording, $segments, fn ($value) => $cipher->encrypt($this->tag($value, $path), $path));
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
        foreach (RecordingPath::resolvePaths($recording, $this->fieldPaths, $this->headerNames) as $segments) {
            $path = implode('.', $segments);
            $recording = RecordingPath::replace($recording, $segments, function ($value) use ($cipher, $path) {
                if (!\is_string($value) || !$cipher->isEncrypted($value)) {
                    return $value;
                }

                return $this->untag($cipher->decrypt($value, $path), $path);
            });
        }

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
