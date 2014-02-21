<?php

namespace VCR\Storage;

interface Storage extends \Iterator
{
    /**
     * Stores an array of data.
     *
     * @return void
     */
    public function storeRecording(array $recording);
}
