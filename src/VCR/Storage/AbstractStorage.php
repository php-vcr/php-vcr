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
     * @var resource File handle.
     */
    protected $handle;

    /**
     * @var string Path to storage file.
     */
    protected $filePath;

    /**
     * @var array Current parsed record.
     */
    protected $current;

    /**
     * @var integer Number of the current recording.
     */
    protected $position = 0;

    /**
     * @var boolean True when parser is at the end of the file.
     */
    protected $isEOF = false;

    /**
     * @var boolean If the cassette file is new.
     */
    protected $isNew = false;

    /**
     * @var boolean If the current position is valid.
     */
    protected $isValidPosition = true;

    /**
     * Creates a new file store.
     *
     * @param string $cassettePath Path to the cassette directory.
     * @param string $cassetteName Path to a file, will be created if not existing.
     */
    public function __construct($cassettePath, $cassetteName, $defaultContent = '[]')
    {
        $file = $cassettePath . DIRECTORY_SEPARATOR . $cassetteName;

        if (!file_exists($file) || 0 === filesize($file)) {
            file_put_contents($file, $defaultContent);

            $this->isNew = true;
        }

        Assertion::file($file, "Specified path '{$file}' is not a file.");
        Assertion::readable($file, "Specified file '{$file}' must be readable.");
        Assertion::writeable($file, "Specified path '{$file}' must be writeable.");

        $this->handle = fopen($file, 'r+');
    }

    /**
     * Returns the current record.
     *
     * @return array Parsed current record.
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Returns the current key.
     *
     * @return integer
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Returns true if the file did not exist and had to be created.
     *
     * @return boolean TRUE if created, FALSE if not
     */
    public function isNew() {
        return $this->isNew;
    }

    /**
     * Closes file handle.
     */
    public function __destruct()
    {
        fclose($this->handle);
    }
}
