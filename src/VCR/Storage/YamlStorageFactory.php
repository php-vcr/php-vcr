<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Creates the YAML storage backend.
 */
class YamlStorageFactory implements StorageFactoryInterface
{
    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        return new Yaml($cassettePath, $cassetteName);
    }
}
