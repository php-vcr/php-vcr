<?php

namespace VCR\Storage;

/**
 * Backhole storage, the storage that looses everything.
 */
class Blackhole implements Storage
{
    /**
     * {@inheritDoc}
     */
    public function storeRecording(array $recording)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function isNew()
    {
        return true;
    }

    public function current()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function key()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    public function next()
    {
    }

    public function rewind()
    {
    }

    public function valid()
    {
        return false;
    }
}
