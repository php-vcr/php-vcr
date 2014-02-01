<?php

namespace VCR\Storage;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use VCR\Util\Assertion;

class Yaml implements Storage
{
    const STATUS_IN_OBJECT = true;
    const STATUS_NOT_IN_OBJECT = false;

    private $handle;
    private $filePath;
    private $recording;
    private $yamlDumper;
    private $yamlParser;
    private $isEOF = false;
    private $valid = true;

    public function __construct($filePath, Parser $parser = null, Dumper $dumper = null)
    {
        if (!file_exists($filePath)) {
            file_put_contents($filePath, '');
        }

        Assertion::file($filePath, "Specified path '{$filePath}' is not a file.");
        Assertion::readable($filePath, "Specified file '{$filePath}' must be readable.");
        Assertion::writeable($filePath, "Specified path '{$filePath}' must be writeable.");

        $this->handle = fopen($filePath, 'r+');
        $this->filePath = $filePath;
        $this->yamlParser = $parser ?: new Parser();
        $this->yamlDumper = $dumper ?: new Dumper();
    }

    public function storeRecording(array $recording)
    {
        fseek($this->handle, -1, SEEK_END);
        fwrite($this->handle, PHP_EOL . $this->yamlDumper->dump(array($recording), 4));
        fflush($this->handle);
    }

    public function current()
    {
        return $this->recording;
    }

    public function key()
    {
    }

    public function next()
    {
        $recording = $this->yamlParser->parse($this->readNextRecording());
        $this->recording = $recording[0];
    }

    private function readNextRecording()
    {
        if ($this->isEOF) {
            $this->valid = false;
        }

        $status = self::STATUS_NOT_IN_OBJECT;
        $recording = '';
        $lastChar = null;

        while (false !== ($char = fgetc($this->handle))) {
            $isNewArrayStart = ($char === '-') && ($lastChar === PHP_EOL || $lastChar === null);
            $lastChar = $char;

            if ($status === self::STATUS_IN_OBJECT && $isNewArrayStart) {
                $status = self::STATUS_NOT_IN_OBJECT;
                fseek($this->handle, -1, SEEK_CUR);
                break;
            }

            if ($status === self::STATUS_NOT_IN_OBJECT && $isNewArrayStart) {
                $status = self::STATUS_IN_OBJECT;
            }

            if ($status === self::STATUS_IN_OBJECT) {
                $recording .= $char;
            }
        }

        if ($char == false) {
            $this->isEOF = true;
        }

        return $recording;
    }

    public function rewind()
    {
        rewind($this->handle);
        $this->isEOF = false;
        $this->valid = true;
    }

    public function valid()
    {
        if (is_null($this->recording)) {
            $this->next();
        }

        return ! is_null($this->recording) && $this->valid;
    }

    public function __destruct()
    {
        fclose($this->handle);
    }

}
