<?php

namespace VCR\Storage;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use VCR\Storage\Storage;
use VCR\Storage\StorageInterface;
use VCR\Util\Assertion;

/**
 * Yaml based storage for records.
 *
 * This storage can be iterated while keeping the memory consumption to the
 * amount of memory used by the largest record.
 */
class Yaml extends Storage implements StorageInterface
{
    /**
     * @var integer Number of the current recording.
     */
    protected $position = 0;

    /**
     * @var boolean If the current position is valid.
     */
    protected $valid = true;

    /**
     * @var Parser Yaml parser.
     */
    protected $yamlParser;

    /**
     * @var  Dumper Yaml writer.
     */
    protected $yamlDumper;

    /**
     * Creates a new YAML based file store.
     *
     * @param string $cassettePath Path to the cassette directory.
     * @param string $cassetteName Path to a file, will be created if not existing.
     * @param Parser $parser Parser used to decode yaml.
     * @param Dumper $dumper Dumper used to encode yaml.
     */
    public function __construct($cassettePath, $cassetteName, Parser $parser = null, Dumper $dumper = null)
    {
        $file = $cassettePath . DIRECTORY_SEPARATOR . $cassetteName;

        if (!file_exists($file)) {
            file_put_contents($file, '');

            $this->new = true;
        }

        Assertion::file($file, "Specified path '{$file}' is not a file.");
        Assertion::readable($file, "Specified file '{$file}' must be readable.");
        Assertion::writeable($file, "Specified path '{$file}' must be writable.");

        $this->handle = fopen($file, 'r+');
        $this->yamlParser = $parser ?: new Parser();
        $this->yamlDumper = $dumper ?: new Dumper();
    }

    /**
     * @inheritDoc
     */
    public function storeRecording(array $recording)
    {
        fseek($this->handle, -1, SEEK_END);
        fwrite($this->handle, "\n" . $this->yamlDumper->dump(array($recording), 4));
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
        $recording = $this->yamlParser->parse($this->readNextRecord());
        $this->current = $recording[0];
        ++$this->position;
    }

    /**
     * Returns the next record in raw format.
     *
     * @return string Next record in raw format.
     */
    private function readNextRecord()
    {
        if ($this->isEOF) {
            $this->valid = false;
        }

        $isInRecord = false;
        $recording = '';
        $lastChar = null;

        while (false !== ($char = fgetc($this->handle))) {
            $isNewArrayStart = ($char === '-') && ($lastChar === "\n" || $lastChar === null);
            $lastChar = $char;

            if ($isInRecord && $isNewArrayStart) {
                fseek($this->handle, -1, SEEK_CUR);
                break;
            }

            if (!$isInRecord && $isNewArrayStart) {
                $isInRecord = true;
            }

            if ($isInRecord) {
                $recording .= $char;
            }
        }

        if ($char == false) {
            $this->isEOF = true;
        }

        return $recording;
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
        $this->valid = true;
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

        return ! is_null($this->current) && $this->valid;
    }
}
