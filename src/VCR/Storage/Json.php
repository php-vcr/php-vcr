<?php

namespace VCR\Storage;

use VCR\Util\Assertion;

/**
 * Json based storage for records.
 *
 * This storage can be iterated while keeping the memory consumption to the
 * amount of memory used by the largest record.
 */
class Json extends AbstractStorage
{
    /** @var string */
    private $buffer = '';

    /**
     * @inheritDoc
     */
    public function storeRecording(array $recording)
    {
        fseek($this->handle, -1, SEEK_END);
        if (ftell($this->handle) > 2) {
            fwrite($this->handle, ',');
        }
        if (defined('JSON_PRETTY_PRINT')) {
            $json = json_encode($recording, JSON_PRETTY_PRINT);
        } else {
            $json = json_encode($recording);
        }
        fwrite($this->handle, $json . ']');
        fflush($this->handle);
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
     * Finds the next JSON-syntax token in the buffer
     *
     * @param string $pattern         Regular expression pattern for expected token
     * @param int    $minBufferLength Minimal required buffer length to satisfy the pattern
     *
     * @return array<int,string> The token and the length of the content prior to it
     */
    private function findNextTokenMatch($pattern, $minBufferLength)
    {
        while (strlen($this->buffer) < $minBufferLength && !feof($this->handle)) {
            $this->buffer .= fread($this->handle, 8192);
        }

        if (preg_match($pattern, $this->buffer, $match, PREG_OFFSET_CAPTURE)) {
            return $match[0];
        }

        return array('', strlen($this->buffer));
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
        $isInLiteral = false;
        $record = '';

        while (true) {
            if (!$isInLiteral) {
                list($token, $offset) = $this->findNextTokenMatch('/[\{\}"]/', 1);
            } else {
                list($token, $offset) = $this->findNextTokenMatch('/(\\\\.|")/', 2);
            }

            switch ($token) {
                case '{':
                    ++$depth;
                    break;
                case '}':
                    --$depth;
                    break;
                case '"':
                    $isInLiteral = !$isInLiteral;
                    break;
            }

            if (!$isInRecord) {
                if ($token === '{') {
                    $isInRecord = true;
                    $record .= $token;
                }
            } else {
                $record .= substr($this->buffer, 0, $offset) . $token;
            }

            $this->buffer = substr($this->buffer, $offset + strlen($token));

            if ($isInRecord && $token === '}' && $depth == 0) {
                break;
            }

            if (strlen($this->buffer) < 1 && feof($this->handle)) {
                $this->isEOF = true;
                break;
            }
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

        $this->buffer = '';
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
}
