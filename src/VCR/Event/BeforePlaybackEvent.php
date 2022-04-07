<?php

declare(strict_types=1);

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;

class BeforePlaybackEvent extends Event
{
    public function __construct(
        protected Request $request,
        protected Cassette $cassette
    ) {
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
