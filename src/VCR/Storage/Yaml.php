<?php

namespace VCR\Storage;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use VCR\Util\Assertion;

/**
 * Yaml based storage for records.
 *
 * This storage can be iterated while keeping the memory consumption to the
 * amount of memory used by the largest record.
 */
class Yaml extends AbstractStorage
{
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
        parent::__construct($cassettePath, $cassetteName, '');

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
            $this->isValidPosition = false;
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
        $this->isValidPosition = true;
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

        return ! is_null($this->current) && $this->isValidPosition;
    }
}
