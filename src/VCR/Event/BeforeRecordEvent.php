<?php

declare(strict_types=1);

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;
use VCR\Response;

class BeforeRecordEvent extends Event
{
    public function __construct(
        protected Request $request,
        protected Response $response,
        protected Cassette $cassette
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

    public function getCassette(): Cassette
    {
        return $this->cassette;
    }
}
