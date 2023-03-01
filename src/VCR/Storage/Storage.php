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
interface Storage extends \Iterator
{
    /**
     * @param array<string,int|string|array<string,mixed>|null> $recording
     */
    public function storeRecording(array $recording): void;

    public function isNew(): bool;
}
