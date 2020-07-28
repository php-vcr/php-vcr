<?php

namespace VCR\Storage;

use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;

/**
 * Yaml based storage for records.
 *
 * This storage can be iterated while keeping the memory consumption to the
 * amount of memory used by the largest record.
 */
class Yaml extends AbstractStorage
{
    /**
     * @var Parser yaml parser
     */
    protected $yamlParser;

    /**
     * @var Dumper yaml writer
     */
    protected $yamlDumper;

    /**
     * Creates a new YAML based file store.
     *
     * @param string $cassettePath path to the cassette directory
     * @param string $cassetteName path to a file, will be created if not existing
     * @param Parser $parser       parser used to decode yaml
     * @param Dumper $dumper       dumper used to encode yaml
     */
    public function __construct($cassettePath, $cassetteName, Parser $parser = null, Dumper $dumper = null)
    {
        parent::__construct($cassettePath, $cassetteName, '');

        $this->yamlParser = $parser ?: new Parser();
        $this->yamlDumper = $dumper ?: new Dumper();
    }

    /**
     * {@inheritdoc}
     */
    public function storeRecording(array $recording): void
    {
        fseek($this->handle, -1, SEEK_END);
        fwrite($this->handle, "\n".$this->yamlDumper->dump([$recording], 4));
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
        $this->current = $recording[0] ?? null;
        ++$this->position;
    }

    /**
     * Returns the next record in raw format.
     *
     * @return string next record in raw format
     */
    private function readNextRecord(): string
    {
        if ($this->isEOF) {
            $this->isValidPosition = false;
        }

        $isInRecord = false;
        $recording = '';

        while (false !== ($line = fgets($this->handle))) {
            $isNewArrayStart = 0 === strpos($line, '-');

            if ($isInRecord && $isNewArrayStart) {
                fseek($this->handle, -\strlen($line), SEEK_CUR);
                break;
            }

            if (!$isInRecord && $isNewArrayStart) {
                $isInRecord = true;
            }

            if ($isInRecord) {
                $recording .= $line;
            }
        }

        if (false == $line) {
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
     * @return bool true if the current record is valid
     */
    public function valid()
    {
        if (null === $this->current) {
            $this->next();
        }

        return null !== $this->current && $this->isValidPosition;
    }
}
