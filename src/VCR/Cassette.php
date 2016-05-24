<?php

namespace VCR;

use VCR\Storage\Storage;
use VCR\Util\Assertion;

/**
 * A Cassette records and plays back pairs of Requests and Responses in a Storage.
 */
class Cassette
{
    /**
     * Casette name
     * @var string
     */
    protected $name;

    /**
     * VCR configuration.
     *
     * @var Configuration
     */
    protected $config;

    /**
     * Storage used to store records and request pairs.
     *
     * @var Storage
     */
    protected $storage;

    /**
     * Creates a new cassette.
     *
     * @param  string           $name    Name of the cassette.
     * @param  Configuration    $config  Configuration to use for this cassette.
     * @param  Storage          $storage Storage to use for requests and responses.
     * @throws \VCR\VCRException If cassette name is in an invalid format.
     */
    public function __construct($name, Configuration $config, Storage $storage)
    {
        Assertion::string($name, 'Cassette name must be a string, ' . \gettype($name) . ' given.');

        $this->name = $name;
        $this->config = $config;
        $this->storage = $storage;
    }

    /**
     * Returns true if a response was recorded for specified request.
     *
     * @param Request $request Request to check if it was recorded.
     *
     * @return boolean True if a response was recorded for specified request.
     */
    public function hasResponse(Request $request)
    {
        try {
            $response = $this->playback($request);
            return $response !== null;
        } catch (\OutOfBoundsException $e) {
            return false;
        }
    }

    /**
     * Returns a response for given request or null if not found.
     *
     * @param Request $request Request.
     *
     * @return Response Response for specified request.
     *
     * @throws \OutOfBoundsException
     *   Thrown if the cassette does not contain the response.
     */
    public function playback(Request $request)
    {
        $storedRequestData = array();
        foreach ($this->storage as $recording) {
            $storedRequest = Request::fromArray($recording['request']);
            if ($storedRequest->matches($request, $this->getRequestMatchers())) {
                return Response::fromArray($recording['response']);
            }
            $storedRequestData[] = $storedRequest->toArray();
        }

        throw new \OutOfBoundsException(
          'Casette does not contain request. It has to be recorded to support playback.'
          . "\nCassette: " . $this->getName()
          . "\nPath: " . $this->config->getCassettePath()
          . "\nRequest: " . print_r($request->toArray(), true)
          . "\nStored requests: " . print_r($storedRequestData, true)
          . "\nRequest matchers: " . print_r($this->config->getRequestMatchers(), true)
        );
    }

    /**
     * Records a request and response pair.
     *
     * @param Request  $request  Request to record.
     * @param Response $response Response to record.
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
     * Returns true if the cassette was created recently.
     *
     * @return boolean
     */
    public function isNew()
    {
        return $this->storage->isNew();
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
