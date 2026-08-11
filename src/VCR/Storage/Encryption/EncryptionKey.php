<?php

declare(strict_types=1);

namespace VCR\Storage\Encryption;

use VCR\Util\Assertion;

/**
 * A 32 byte master key from which two role-specific subkeys are derived.
 *
 * The nonce is derived from the plaintext, so the key doing that derivation must not be the key that
 * encrypts — otherwise one key would serve two roles.
 */
class EncryptionKey
{
    private const CONTEXT = 'vcrenc01';

    private const SUBKEY_ENCRYPTION = 1;

    private const SUBKEY_NONCE = 2;

    /**
     * Nullable because sodium_memzero() in __destruct() nullifies this by-reference argument.
     */
    private ?string $master;

    private function __construct(string $master)
    {
        $this->master = $master;
    }

    /**
     * @param string $master raw 32 byte key
     *
     * @throws \VCR\VCRException if the key does not have the required length
     */
    public static function fromBinary(string $master): self
    {
        Assertion::length(
            $master,
            \SODIUM_CRYPTO_KDF_KEYBYTES,
            \sprintf('An encryption key must be exactly %d bytes.', \SODIUM_CRYPTO_KDF_KEYBYTES),
            null,
            '8bit'
        );

        return new self($master);
    }

    /**
     * @throws \VCR\VCRException if the input is not base64 or does not decode to the required length
     */
    public static function fromBase64(string $master): self
    {
        $decoded = base64_decode($master, true);

        Assertion::string($decoded, 'An encryption key must be valid base64.');

        return self::fromBinary($decoded);
    }

    public static function generate(): self
    {
        return new self(sodium_crypto_kdf_keygen());
    }

    public function encryptionKey(): string
    {
        Assertion::notNull($this->master, 'The master key must be available.');

        return sodium_crypto_kdf_derive_from_key(
            \SODIUM_CRYPTO_KDF_KEYBYTES,
            self::SUBKEY_ENCRYPTION,
            self::CONTEXT,
            $this->master
        );
    }

    public function nonceKey(): string
    {
        Assertion::notNull($this->master, 'The master key must be available.');

        return sodium_crypto_kdf_derive_from_key(
            \SODIUM_CRYPTO_KDF_KEYBYTES,
            self::SUBKEY_NONCE,
            self::CONTEXT,
            $this->master
        );
    }

    public function toBase64(): string
    {
        Assertion::notNull($this->master, 'The master key must be available.');

        return base64_encode($this->master);
    }

    public function __destruct()
    {
        sodium_memzero($this->master);
    }
}
