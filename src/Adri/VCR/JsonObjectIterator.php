<?php

namespace Adri\VCR;

class JsonObjectIterator implements \Iterator
{
    const STATUS_IN_OBJECT = true;
    const STATUS_NOT_IN_OBJECT = false;

    private $handle;
    private $currentJson;
    private $isEOF = false;

    public function __construct($fileHandle)
    {
        $this->handle = $fileHandle;
    }

    public function current()
    {
        return $this->currentJson;
        // $firstChar = fgetc($this->handle);
        // if ($firstChar !== '[') {
        //     throw new \InvalidArgumentException('File has to start with [, like a json array.');
        // }
    }

    public function key()
    {
    }

    public function next()
    {
        $this->currentJson = json_decode($this->readNextJsonString(), true);
    }

    private function readNextJsonString()
    {
        $depth = 0;
        $status = self::STATUS_NOT_IN_OBJECT;
        $currentJson = '';

        while (false !== ($char = fgetc($this->handle))) {
            if ($char === '{') {++$depth;}
            if ($char === '}') {--$depth;}

            if ($status === self::STATUS_NOT_IN_OBJECT && $char === '{') {
                $status = self::STATUS_IN_OBJECT;
            }

            if ($status === self::STATUS_IN_OBJECT) {
                $currentJson .= $char;
            }

            if ($status === self::STATUS_IN_OBJECT && $char === '}' && $depth == 0) {
                $status = self::STATUS_NOT_IN_OBJECT;
                break;
            }
        }

        if ($char == false) {
            $this->isEOF = true;
        }

        return $currentJson;
    }

    public function rewind()
    {
        rewind($this->handle);
        $this->isEOF = false;
    }

    public function valid()
    {
        if (is_null($this->currentJson)) {
            $this->next();
        }
        return !$this->isEOF;
    }

}
