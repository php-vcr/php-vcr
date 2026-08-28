<?php

declare(strict_types=1);

namespace VCR\Storage;

use VCR\Storage\Encryption\CipherInterface;
use VCR\Storage\Encryption\EncryptionPolicy;

/**
 * Encrypted storage over an inner storage that can be purged.
 *
 * Kept separate from EncryptedStorage because the wrapped storage is only sometimes purgeable —
 * implementing PurgeableStorageInterface unconditionally and throwing at runtime would break
 * substitutability.
 */
class PurgeableEncryptedStorage extends EncryptedStorage implements PurgeableStorageInterface
{
    private PurgeableStorageInterface $storage;

    public function __construct(PurgeableStorageInterface $storage, CipherInterface $cipher, EncryptionPolicy $policy)
    {
        parent::__construct($storage, $cipher, $policy);

        $this->storage = $storage;
    }

    public function purge(): void
    {
        $this->storage->purge();
    }
}
