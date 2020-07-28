<?php

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
     * @var resource file handle
     */
    protected $handle;

    /**
     * @var string path to storage file
     */
    protected $filePath;

    /**
     * @var array<string,mixed>|null current parsed record
     */
    protected $current;

    /**
     * @var int number of the current recording
     */
    protected $position = 0;

    /**
     * @var bool true when parser is at the end of the file
     */
    protected $isEOF = false;

    /**
     * @var bool if the cassette file is new
     */
    protected $isNew = false;

    /**
     * @var bool if the current position is valid
     */
    protected $isValidPosition = true;

    /**
     * Creates a new file store.
     *
     * If the cassetteName contains PATH_SEPARATORs, subfolders of the
     * cassettePath are autocreated when not existing.
     *
     * @param string $cassettePath   path to the cassette directory
     * @param string $cassetteName   path to the cassette file, relative to the path
     * @param string $defaultContent Default data for this cassette if its not existing
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
    public function current()
    {
        return $this->current;
    }

    /**
     * Returns the current key.
     *
     * @return int
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Returns true if the file did not exist and had to be created.
     *
     * @return bool TRUE if created, FALSE if not
     */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /**
     * Closes file handle.
     */
    public function __destruct()
    {
        if (\is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
