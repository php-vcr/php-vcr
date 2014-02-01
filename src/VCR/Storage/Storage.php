<?php

namespace VCR\Storage;

interface Storage extends \Iterator
{
    /**
     * @return void
     */
    public function storeRecording(array $recording);
}
