<?php

declare(strict_types=1);

namespace VCR\Storage\Encryption;

/**
 * Encrypts and decrypts a single cassette field.
 *
 * The field path is bound into the ciphertext, so a value cannot be moved to another field without
 * decryption failing.
 */
interface CipherInterface
{
    /**
     * Encrypts a single value and marks the result so it can be recognised again.
     *
     * @param string $plaintext the raw value as it would otherwise be written to the cassette
     * @param string $fieldPath dot path of the field the value belongs to, bound into the ciphertext
     *
     * @return string marked ciphertext, safe to write to a cassette
     */
    public function encrypt(string $plaintext, string $fieldPath): string;

    /**
     * Decrypts a value previously produced by encrypt().
     *
     * @param string $ciphertext marked ciphertext as read from the cassette
     * @param string $fieldPath  dot path of the field the value was read from; must be the same path
     *                           that was used to encrypt it
     *
     * @return string the original plaintext
     *
     * @throws DecryptionFailedException  if the key is wrong, the value was altered, or it belongs
     *                                    to a different field
     * @throws UnsupportedSchemeException if the value was written by a newer encryption scheme
     */
    public function decrypt(string $ciphertext, string $fieldPath): string;

    /**
     * Tells whether a value carries the encryption marker.
     *
     * Values without the marker are passed through untouched, which keeps cassettes readable that
     * were recorded before encryption was enabled.
     *
     * @param string $value value as read from the cassette
     */
    public function isEncrypted(string $value): bool;
}
