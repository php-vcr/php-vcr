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
    // Extends Storage (in addition to PurgeableStorageInterface) purely for backwards compatibility:
    // pre-1.12 user code may type-hint against Storage or check "instanceof \VCR\Storage\Storage".
    // Dropping Storage from this hierarchy would silently break that code, since Yaml/Json/Blackhole
    // are the concrete classes such checks target.
}
