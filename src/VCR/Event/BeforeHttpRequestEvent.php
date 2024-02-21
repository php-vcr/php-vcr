<?php

declare(strict_types=1);

namespace VCR\Event;

use VCR\Request;

class BeforeHttpRequestEvent extends Event
{
    public function __construct(
        protected Request $request
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
