<?php

declare(strict_types=1);

namespace VCR\Event;

use VCR\Request;
use VCR\Response;

class AfterHttpRequestEvent extends Event
{
    public function __construct(
        protected Request $request,
        protected Response $response
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
