<?php

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;

class BeforePlaybackEvent extends Event
{
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Cassette
     */
    protected $cassette;

    public function __construct(Request $request, Cassette $cassette)
    {
        $this->request = $request;
        $this->cassette = $cassette;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getCassette(): Cassette
    {
        return $this->cassette;
    }
}
