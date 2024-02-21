<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Backhole storage, the storage that looses everything.
 */
class Blackhole implements Storage
{
    public function storeRecording(array $recording): void
    {
    }

    public function isNew(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function current(): ?array
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function key(): int
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function next(): void
    {
    }

    public function rewind(): void
    {
    }

    public function valid(): bool
    {
        return false;
    }
}
