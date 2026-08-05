<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Creates the Blackhole storage backend, which discards everything.
 *
 * Cassette path and name are irrelevant — Blackhole never touches the
 * filesystem.
 */
class BlackholeStorageFactory implements StorageFactoryInterface
{
    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        return new Blackhole();
    }
}
