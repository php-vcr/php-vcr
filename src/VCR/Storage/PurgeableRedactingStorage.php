<?php

declare(strict_types=1);

namespace VCR\Storage;

use VCR\Storage\Redaction\RedactionRules;

/**
 * Redacting storage over an inner storage that can be purged.
 *
 * Kept separate from RedactingStorage because the wrapped storage is only sometimes purgeable —
 * implementing PurgeableStorageInterface unconditionally and throwing at runtime would break
 * substitutability.
 */
class PurgeableRedactingStorage extends RedactingStorage implements PurgeableStorageInterface
{
    private PurgeableStorageInterface $storage;

    public function __construct(PurgeableStorageInterface $storage, RedactionRules $rules)
    {
        parent::__construct($storage, $rules);

        $this->storage = $storage;
    }

    public function purge(): void
    {
        $this->storage->purge();
    }
}
