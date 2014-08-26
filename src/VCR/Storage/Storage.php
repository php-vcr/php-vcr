<?php

namespace VCR\Storage;

use VCR\Util\Assertion;

/**
 * Abstract base for storing records.
 */
class Storage
{
    /**
     * @var resource File handle.
     */
    protected $handle;

    /**
     * @var array Current parsed record.
     */
    protected $current;

    /**
     * @var boolean True when parser is at the end of the file.
     */
    protected $isEOF = false;

    /**
     * @var boolean If the cassette file is new.
     */
    protected $new = false;

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
     * @inheritDoc
     */
    public function isNew()
    {
        return $this->new;
    }

    /**
     * Closes file handle.
     */
    public function __destruct()
    {
        fclose($this->handle);
    }
}