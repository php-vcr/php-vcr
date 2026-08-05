<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Creates a Storage bound to a concrete cassette.
 *
 * Implement this to plug in a custom storage backend, for example one backed
 * by a database. Own dependencies (connections, clients, ...) are injected
 * into the factory and handed on to the created Storage. Register the factory
 * via \VCR\Configuration::setStorageFactory().
 */
interface StorageFactoryInterface
{
    /**
     * @param string $cassettePath directory the cassette lives in
     * @param string $cassetteName file name or identifier of the cassette
     *
     * @return StorageInterface storage bound to the given cassette
     */
    public function create(string $cassettePath, string $cassetteName): StorageInterface;
}
