<?php

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;
use Symfony\Component\EventDispatcher\Event;

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

    /**
     * @param Request $request
     * @param Cassette $cassette
     */
    public function __construct(Request $request, Cassette $cassette)
    {
        $this->request = $request;
        $this->cassette = $cassette;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return Cassette
     */
    public function getCassette(): Cassette
    {
        return $this->cassette;
    }
}
