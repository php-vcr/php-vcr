<?php

namespace VCR\Storage;

interface StorageInterface extends \Iterator
{
    public function storeRecording(array $recording);
}
