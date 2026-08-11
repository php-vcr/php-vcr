<?php

declare(strict_types=1);

namespace VCR\Storage;

use VCR\Storage\Encryption\CipherInterface;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\Storage\Encryption\EncryptionPolicy;
use VCR\Storage\Encryption\SodiumCipher;

/**
 * Wraps another storage factory so every cassette it creates is encrypted.
 */
class EncryptedStorageFactory implements StorageFactoryInterface
{
    private StorageFactoryInterface $storageFactory;

    private CipherInterface $cipher;

    private EncryptionPolicy $policy;

    public function __construct(
        StorageFactoryInterface $storageFactory,
        CipherInterface $cipher,
        ?EncryptionPolicy $policy = null
    ) {
        $this->storageFactory = $storageFactory;
        $this->cipher = $cipher;
        $this->policy = $policy ?? new EncryptionPolicy();
    }

    public static function withKey(
        StorageFactoryInterface $storageFactory,
        EncryptionKey $key,
        ?EncryptionPolicy $policy = null
    ): self {
        return new self($storageFactory, new SodiumCipher($key), $policy);
    }

    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        $storage = $this->storageFactory->create($cassettePath, $cassetteName);

        if ($storage instanceof PurgeableStorageInterface) {
            return new PurgeableEncryptedStorage($storage, $this->cipher, $this->policy);
        }

        return new EncryptedStorage($storage, $this->cipher, $this->policy);
    }
}
