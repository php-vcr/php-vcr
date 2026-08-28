<?php

declare(strict_types=1);

namespace VCR\Storage\Encryption;

/**
 * XChaCha20-Poly1305 with a nonce derived from the plaintext.
 *
 * Deriving the nonce instead of drawing it at random keeps a re-recorded cassette byte-identical, so
 * re-recording produces no git diff. The cost is that equal plaintexts within the same field produce
 * equal ciphertexts.
 */
class SodiumCipher implements CipherInterface
{
    private const MARKER = 'vcr:enc:';

    private const SCHEME = 'v1';

    private EncryptionKey $key;

    public function __construct(EncryptionKey $key)
    {
        if (!\function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            throw new \RuntimeException('The encrypted storage requires ext-sodium, which is not loaded.');
        }

        $this->key = $key;
    }

    public function encrypt(string $plaintext, string $fieldPath): string
    {
        $additionalData = $this->additionalData($fieldPath);
        $nonce = sodium_crypto_generichash(
            $additionalData."\0".$plaintext,
            $this->key->nonceKey(),
            \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
        );

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $additionalData,
            $nonce,
            $this->key->encryptionKey()
        );

        return self::MARKER.self::SCHEME.':'.base64_encode($nonce.$ciphertext);
    }

    public function decrypt(string $ciphertext, string $fieldPath): string
    {
        $payload = substr($ciphertext, \strlen(self::MARKER));
        $separator = strpos($payload, ':');

        if (false === $separator) {
            throw DecryptionFailedException::malformed($fieldPath);
        }

        $scheme = substr($payload, 0, $separator);

        if (self::SCHEME !== $scheme) {
            throw UnsupportedSchemeException::forScheme($scheme, $fieldPath);
        }

        $binary = base64_decode(substr($payload, $separator + 1), true);

        if (!\is_string($binary) || \strlen($binary) <= \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw DecryptionFailedException::malformed($fieldPath);
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($binary, \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $this->additionalData($fieldPath),
            substr($binary, 0, \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $this->key->encryptionKey()
        );

        if (!\is_string($plaintext)) {
            throw DecryptionFailedException::forField($fieldPath);
        }

        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::MARKER);
    }

    private function additionalData(string $fieldPath): string
    {
        return self::SCHEME.'|'.$fieldPath;
    }
}
