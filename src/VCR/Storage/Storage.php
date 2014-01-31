<?php

namespace VCR\Storage;

interface Storage extends \Iterator
{
    public function storeRecording(array $recording);
}
