<?php

namespace VCR;

use VCR\Storage\StorageInterface;
use VCR\Assertion;

/**
 * A Cassette records and plays back pairs of Requests and Responses in a Storage.
 */
class Cassette
{
    /**
     * @var string Cassette name.
     */
    protected $name;

    /**
     * @var \VCR\Configuration
     */
    protected $config;

    /**
     * @var \VCR\Storage\StorageInterface
     */
    protected $storage;

    /**
     * Creates a new cassette.
     *
     * @param string                        $name    Name of the cassette.
     * @param \VCR\Configuration            $config  Configuration to use for this cassette.
     * @param \VCR\Storage\StorageInterface $storage Storage to use for requests and responses.
     * @throws \VCR\VCRException If cassette name is in an invalid format.
     */
    public function __construct($name, Configuration $config, StorageInterface $storage)
    {
        Assertion::string($name, "Cassette name must be a string, " . \gettype($name) . " given.");

        $this->name = $name;
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * Returns true if a response was recorded for specified request.
     *
     * @param  Request $request Request to check if it was recorded.
     *
     * @return boolean          True if a resposne was recorded for specified request.
     */
    public function hasResponse(Request $request)
    {
        return $this->playback($request) !== null;
    }

    /**
     * Returns a response for given request or null if not found.
     *
     * @param  Request $request Request.
     *
     * @return string Response for specified request.
     */
    public function playback(Request $request)
    {
        foreach ($this->storage as $recording) {
            $storedRequest = Request::fromArray($recording['request']);
            if ($storedRequest->matches($request, $this->getRequestMatchers())) {
                return Response::fromArray($recording['response']);
            }
        }

        return null;
    }

    /**
     * Records a request and response pair.
     *
     * @param  Request $request   Request to record.
     * @param  Response $response Response to record.
     *
     * @return void
     */
    public function record(Request $request, Response $response)
    {
        if ($this->hasResponse($request)) {
            return;
        }

        $recording = array(
            'request'  => $request->toArray(),
            'response' => $response->toArray()
        );

        $this->storage->storeRecording($recording);
    }

    /**
     * Returns the name of the current cassette.
     *
     * @return string Current cassette name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns a list of callbacks to configured request matchers.
     *
     * @return array List of callbacks to configured request matchers.
     */
    protected function getRequestMatchers()
    {
        return $this->config->getRequestMatchers();
    }
}
