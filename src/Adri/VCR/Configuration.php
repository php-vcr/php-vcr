<?php

namespace Adri\VCR;

/**
 * Configuration.
 */
class Configuration
{
    private $cassettePath = 'tests/fixtures';

    private $libraryHooks = array(
        '\Adri\VCR\LibraryHooks\StreamWrapper',
        '\Adri\VCR\LibraryHooks\Curl',
    );

    private $turnOnAutomatically = true;

    public function getTurnOnAutomatically()
    {
        return $this->turnOnAutomatically;
    }

    public function getCassettePath()
    {
        return $this->cassettePath;
    }

    public function getLibraryHooks()
    {
        return $this->libraryHooks;
    }

    public function setCassettePath($cassettePath)
    {
        $this->cassettePath = $cassettePath;
        return $this;
    }
}
