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
}
