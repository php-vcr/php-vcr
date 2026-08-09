<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Creates the JSON storage backend.
 */
class JsonStorageFactory implements StorageFactoryInterface
{
    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        return new Json($cassettePath, $cassetteName);
    }
}
