<?php

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;
use VCR\Response;
use Symfony\Component\EventDispatcher\Event;

class AfterPlaybackEvent extends Event
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
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return Cassette
     */
    public function getCassette()
    {
        return $this->cassette;
    }
}
