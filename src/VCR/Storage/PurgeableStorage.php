<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Storage that can be purged, i.e. reset to its empty/default state.
 *
 * Under record mode "all" the cassette is purged on insert so every run
 * starts from a clean recording.
 */
interface PurgeableStorage extends Storage
{
    /**
     * Purge all stored recordings and reset the storage to its empty state.
     */
    public function purge(): void;
}
