<?php

namespace VCR\Storage;

use VCR\Util\Assertion;

/**
 * Json based storage for records.
 *
 * This storage can be iterated while keeping the memory consumption to the
 * amount of memory used by the largest record.
 *
 */
class Json implements Storage
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
    protected $new = false;

    /**
     * Creates a new JSON based file store.
     *
     * @param string $cassettePath Path to the cassette directory.
     * @param string $cassetteName Path to a file, will be created if not existing.
     */
    public function __construct($cassettePath, $cassetteName)
    {
        $file = $cassettePath . DIRECTORY_SEPARATOR . $cassetteName;

        if (!file_exists($file)) {
            file_put_contents($file, '[]');

            $this->new = true;
        }

        Assertion::file($file, "Specified path '{$file}' is not a file.");
        Assertion::readable($file, "Specified file '{$file}' must be readable.");
        Assertion::writeable($file, "Specified path '{$file}' must be writeable.");

        $this->handle = fopen($file, 'r+');
        $this->filePath = $file;
    }

    /**
     * @inheritDoc
     */
    public function storeRecording(array $recording)
    {
        fseek($this->handle, -1, SEEK_END);
        if (filesize($this->filePath) > 2) {
            fwrite($this->handle, ',');
        }
        fwrite($this->handle, json_encode($recording) . ']');
        fflush($this->handle);
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
     * Parses the next record.
     *
     * @return void
     */
    public function next()
    {
        $this->current = json_decode($this->readNextRecord(), true);
        ++$this->position;
    }

    /**
     * Returns the next record in raw format.
     *
     * @return string Next record in raw format.
     */
    protected function readNextRecord()
    {
        $depth = 0;
        $isInRecord = false;
        $record = '';

        while (false !== ($char = fgetc($this->handle))) {
            if ($char === '{') {++$depth;}
            if ($char === '}') {--$depth;}

            if (!$isInRecord && $char === '{') {
                $isInRecord = true;
            }

            if ($isInRecord) {
                $record .= $char;
            }

            if ($isInRecord && $char === '}' && $depth == 0) {
                break;
            }
        }

        if ($char == false) {
            $this->isEOF = true;
        }

        return $record;
    }

    /**
     * Resets the storage to the beginning.
     *
     * @return void
     */
    public function rewind()
    {
        rewind($this->handle);
        $this->isEOF = false;
        $this->position = 0;
    }

    /**
     * Returns true if the current record is valid.
     *
     * @return boolean True if the current record is valid.
     */
    public function valid()
    {
        if (is_null($this->current)) {
            $this->next();
        }

        return !$this->isEOF;
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
