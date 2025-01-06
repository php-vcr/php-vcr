<?php

declare(strict_types=1);

namespace VCR\Storage;

/**
 * Json based storage for records.
 *
 * This storage can be iterated while keeping the memory consumption to the
 * amount of memory used by the largest record.
 */
class Json extends AbstractStorage
{
    public function storeRecording(array $recording): void
    {
        fseek($this->handle, -1, \SEEK_END);
        if (ftell($this->handle) > 2) {
            fwrite($this->handle, ',');
        }
        if (\defined('JSON_PRETTY_PRINT')) {
            $json = json_encode($recording, \JSON_PRETTY_PRINT);
        } else {
            $json = json_encode($recording);
        }
        fwrite($this->handle, $json.']');
        fflush($this->handle);
    }

    public function next(): void
    {
        $this->current = json_decode($this->readNextRecord(), true);
        ++$this->position;
    }

    /**
     * Returns the next record in raw format.
     */
    protected function readNextRecord(): string
    {
        $depth = 0;
        $isInRecord = false;
        $record = '';

        while (false !== ($char = fgetc($this->handle))) {
            if ('{' === $char) {
                ++$depth;
            }
            if ('}' === $char) {
                --$depth;
            }

            if (!$isInRecord && '{' === $char) {
                $isInRecord = true;
            }

            if ($isInRecord) {
                $record .= $char;
            }

            if ($isInRecord && '}' === $char && 0 == $depth) {
                break;
            }
        }

        if (false == $char) {
            $this->isEOF = true;
        }

        return $record;
    }

    public function rewind(): void
    {
        rewind($this->handle);
        $this->isEOF = false;
        $this->position = 0;
    }

    public function valid(): bool
    {
        if (null === $this->current) {
            $this->next();
        }

        return !$this->isEOF;
    }
}
