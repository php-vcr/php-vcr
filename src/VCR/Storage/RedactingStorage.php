<?php

declare(strict_types=1);

namespace VCR\Storage;

use VCR\Storage\Redaction\RedactionRules;

/**
 * Redacts sensitive fields on the way to the wrapped storage and restores them on the way back.
 *
 * Restoration happens in current(), which runs before Cassette::playback() applies the request
 * matchers — so matching keeps working on the restored data while only the redacted data reaches
 * the disk. storeRecording() applies every rule's redact() in registration order, each rule
 * receiving the previous rule's output; current() applies restore() in the reverse order, so a
 * chain of transforms is actually inverted rather than each rule trying (and failing) to undo a
 * recording it never produced.
 */
class RedactingStorage implements StorageInterface
{
    private StorageInterface $storage;

    private RedactionRules $rules;

    public function __construct(StorageInterface $storage, RedactionRules $rules)
    {
        $this->storage = $storage;
        $this->rules = $rules;
    }

    public function storeRecording(array $recording): void
    {
        foreach ($this->rules->rules() as $rule) {
            $recording = $rule->redact($recording);
        }

        $this->storage->storeRecording($recording);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function current(): ?array
    {
        /** @var array<string,mixed>|null $current */
        $current = $this->storage->current();

        if (!\is_array($current)) {
            return null;
        }

        foreach (array_reverse($this->rules->rules()) as $rule) {
            $current = $rule->restore($current);
        }

        return $current;
    }

    public function next(): void
    {
        $this->storage->next();
    }

    public function key(): int
    {
        return $this->storage->key();
    }

    public function rewind(): void
    {
        $this->storage->rewind();
    }

    public function valid(): bool
    {
        return $this->storage->valid();
    }

    public function isNew(): bool
    {
        return $this->storage->isNew();
    }
}
