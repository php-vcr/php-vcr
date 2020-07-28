<?php

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;
use VCR\Response;

class BeforeRecordEvent extends Event
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Cassette
     */
    protected $cassette;

    public function __construct(Request $request, Response $response, Cassette $cassette)
    {
        $this->request = $request;
        $this->response = $response;
        $this->cassette = $cassette;
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
