<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Storage that can be purged, i.e. reset to its empty/default state.
 *
 * @deprecated since 1.12, use {@see PurgeableStorageInterface}. Kept as a
 *             backwards-compatible alias, removed in the next major.
 */
interface PurgeableStorage extends Storage, PurgeableStorageInterface
{
    // Extends Storage (in addition to PurgeableStorageInterface) to maintain the type hierarchy:
    // VCRFactory::createStorage() asserts that storage classes are subclasses of Storage.
    // Concrete implementations (Yaml, Json, Blackhole) extend AbstractStorage which implements
    // PurgeableStorage; they must remain subtypes of Storage for VCRFactory's runtime check.
    // Removing Storage from the hierarchy would break type assertions without compile-time signal.
}
