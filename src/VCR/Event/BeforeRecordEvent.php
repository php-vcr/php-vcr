<?php

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;
use VCR\Response;
use Symfony\Component\EventDispatcher\Event;

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

    /**
     * @param Request $request
     * @param Response $response
     * @param Cassette $cassette
     */
    public function __construct(Request $request, Response $response, Cassette $cassette)
    {
        $this->request = $request;
        $this->response = $response;
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
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return Cassette
     */
    public function getCassette(): Cassette
    {
        return $this->cassette;
    }
}
