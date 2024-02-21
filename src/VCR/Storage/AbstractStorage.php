<?php

declare(strict_types=1);

namespace VCR\Storage;

use VCR\Util\Assertion;

/**
 * Abstract base for reading and storing records.
 *
 * A Storage can be iterated using standard loops.
 * New recordings can be stored.
 */
abstract class AbstractStorage implements Storage
{
    /**
     * @var resource
     */
    protected $handle;

    protected string $filePath;

    /**
     * @var array<string,mixed>|null current parsed record
     */
    protected ?array $current = null;

    protected int $position = 0;

    protected bool $isEOF = false;

    protected bool $isNew = false;

    protected bool $isValidPosition = true;

    /**
     * If the cassetteName contains PATH_SEPARATORs, subfolders of the
     * cassettePath are autocreated when not existing.
     */
    public function __construct(string $cassettePath, string $cassetteName, string $defaultContent = '[]')
    {
        Assertion::directory($cassettePath, "Cassette path '{$cassettePath}' is not existing or not a directory");

        $this->filePath = rtrim($cassettePath, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.$cassetteName;

        if (!is_dir(\dirname($this->filePath))) {
            mkdir(\dirname($this->filePath), 0777, true);
        }

        if (!file_exists($this->filePath) || 0 === filesize($this->filePath)) {
            file_put_contents($this->filePath, $defaultContent);
            $this->isNew = true;
        }

        Assertion::file($this->filePath, "Specified path '{$this->filePath}' is not a file.");
        Assertion::readable($this->filePath, "Specified file '{$this->filePath}' must be readable.");

        $handle = fopen($this->filePath, 'r+');

        Assertion::isResource($handle);

        $this->handle = $handle;
    }

    /**
     * Returns the current record.
     *
     * @return array<string,mixed>|null parsed current record
     */
    public function current(): ?array
    {
        return $this->current;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function __destruct()
    {
        if (\is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
