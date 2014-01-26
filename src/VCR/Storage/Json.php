<?php

namespace VCR\Storage;

use VCR\Assertion;

class Json implements StorageInterface
{
    const STATUS_IN_OBJECT = true;
    const STATUS_NOT_IN_OBJECT = false;

    private $handle;
    private $filePath;
    private $currentJson;
    private $isEOF = false;

    public function __construct($filePath)
    {
        if (!file_exists($filePath)) {
            file_put_contents($filePath, '[]');
        }

        Assertion::file($filePath, "Specified path '{$filePath}' is not a file.");
        Assertion::readable($filePath, "Specified file '{$filePath}' must be readable.");
        Assertion::writeable($filePath, "Specified path '{$filePath}' must be writeable.");

        $this->handle = fopen($filePath, 'r+');
        $this->filePath = $filePath;
    }

    public function storeRecording(array $recording)
    {
        fseek($this->handle, -1, SEEK_END);
        if (filesize($this->filePath) > 2) {
            fwrite($this->handle, ',');
        }
        fwrite($this->handle, json_encode($recording) . ']');
        fflush($this->handle);
    }

    public function current()
    {
        return $this->currentJson;
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

    public function __destruct()
    {
        fclose($this->handle);
    }

}
