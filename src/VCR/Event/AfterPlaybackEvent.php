<?php

namespace VCR\Event;

use VCR\Cassette;
use VCR\Request;
use VCR\Response;
use Symfony\Component\EventDispatcher\Event;
use VCR\Util\Assertion;

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
     * @param Response|null $response
     * @param Cassette $cassette
     */
    public function __construct(Request $request, $response, Cassette $cassette)
    {
        $this->request = $request;
        Assertion::nullOrIsInstanceOf($response, 'VCR\Response');
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
     * Returns the response for the intercepted request
     * or null if there is no response matching to the request.
     *
     * @return null|Response
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
