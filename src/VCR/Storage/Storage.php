<?php

namespace VCR\Storage;

/**
 * Interface for reading and storing records.
 *
 * A Storage can be iterated using standard loops.
 * New recordings can be stored.
 *
 * @phpstan-extends \Iterator<int, array>
 */
interface Storage extends \Iterator
{
    /**
     * Stores an array of data.
     *
     * @param array<string,array<string,mixed>> $recording array to store in storage
     */
    public function storeRecording(array $recording): void;

    /**
     * Returns true if the file did not exist and had to be created.
     *
     * @return bool TRUE if created, FALSE if not
     */
    public function isNew(): bool;
}
