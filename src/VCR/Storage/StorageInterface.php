<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Interface for reading and storing records.
 *
 * A Storage can be iterated using standard loops.
 * New recordings can be stored.
 *
 * @phpstan-extends \Iterator<int, array>
 */
interface StorageInterface extends \Iterator
{
    /**
     * Appends a single recording to the storage.
     *
     * @param array<string,int|string|array<string,mixed>|null> $recording
     */
    public function storeRecording(array $recording): void;

    /**
     * Whether this Storage started out empty — the cassette did not exist yet, or was just purged.
     */
    public function isNew(): bool;
}
