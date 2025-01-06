<?php

declare(strict_types=1);

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
    protected Parser $yamlParser;

    protected Dumper $yamlDumper;

    public function __construct(string $cassettePath, string $cassetteName, ?Parser $parser = null, ?Dumper $dumper = null)
    {
        parent::__construct($cassettePath, $cassetteName, '');

        $this->yamlParser = $parser ?: new Parser();
        $this->yamlDumper = $dumper ?: new Dumper();
    }

    public function storeRecording(array $recording): void
    {
        fseek($this->handle, -1, \SEEK_END);
        fwrite($this->handle, "\n".$this->yamlDumper->dump([$recording], 4));
        fflush($this->handle);
    }

    public function next(): void
    {
        $recording = $this->yamlParser->parse($this->readNextRecord());
        $this->current = $recording[0] ?? null;
        ++$this->position;
    }

    /**
     * Returns the next record in raw format.
     */
    private function readNextRecord(): string
    {
        if ($this->isEOF) {
            $this->isValidPosition = false;
        }

        $isInRecord = false;
        $recording = '';

        while (false !== ($line = fgets($this->handle))) {
            $isNewArrayStart = str_starts_with($line, '-');

            if ($isInRecord && $isNewArrayStart) {
                fseek($this->handle, -\strlen($line), \SEEK_CUR);
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

    public function rewind(): void
    {
        rewind($this->handle);
        $this->isEOF = false;
        $this->isValidPosition = true;
        $this->position = 0;
    }

    public function valid(): bool
    {
        if (null === $this->current) {
            $this->next();
        }

        return null !== $this->current && $this->isValidPosition;
    }
}
