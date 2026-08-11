<?php

declare(strict_types=1);

namespace VCR\Storage;

use VCR\Storage\Encryption\CipherInterface;
use VCR\Storage\Encryption\EncryptionPolicy;

/**
 * Encrypts sensitive fields on the way to the wrapped storage and decrypts them on the way back.
 *
 * Decryption happens in current(), which runs before Cassette::playback() applies the request
 * matchers — so matching keeps working on plaintext while only ciphertext reaches the disk.
 */
class EncryptedStorage implements StorageInterface
{
    private StorageInterface $storage;

    private CipherInterface $cipher;

    private EncryptionPolicy $policy;

    public function __construct(StorageInterface $storage, CipherInterface $cipher, EncryptionPolicy $policy)
    {
        $this->storage = $storage;
        $this->cipher = $cipher;
        $this->policy = $policy;
    }

    public function storeRecording(array $recording): void
    {
        $this->storage->storeRecording($this->policy->encrypt($recording, $this->cipher));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function current(): ?array
    {
        /** @var array<string,mixed>|null $current */
        $current = $this->storage->current();

        if (!\is_array($current)) {
            return null;
        }

        return $this->policy->decrypt($current, $this->cipher);
    }

    public function next(): void
    {
        $this->storage->next();
    }

    public function key(): int
    {
        return $this->storage->key();
    }

    public function rewind(): void
    {
        $this->storage->rewind();
    }

    public function valid(): bool
    {
        return $this->storage->valid();
    }

    public function isNew(): bool
    {
        return $this->storage->isNew();
    }
}
