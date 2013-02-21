<?php

namespace Adri\VCR;

/**
 * Todo: Extract Storage class, Iterator, appendRecord, readFromDisk
 */
class Cassette
{
    protected $name;
    protected $config;
    protected $hasReadFromDisk = false;
    protected $storage;
    protected $cassetteHandle;

    function __construct($name, Configuration $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->storage = $this->createStorage();
    }

    public function hasResponse(Request $request)
    {
        return $this->playback($request) !== null;
    }

    /**
     * Returns a response for given request or null if not found.
     *
     * @param  Request $request Request.
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

    public function record(Request $request, $response)
    {
        if ($this->hasResponse($request)) {
            return;
        }

        $this->storage->storeRecording($request, $response);
    }

    public function getName()
    {
        return $this->name;
    }

    protected function getCassettePath()
    {
        return $this->config->getCassettePath() . DIRECTORY_SEPARATOR . $this->name;
    }

    public function createStorage()
    {
        // $class = $this->config->getStorageClass();
        return new Storage\Json($this->getCassettePath());
    }

    public function getRequestMatchers()
    {
        return $this->config->getRequestMatchers();
    }
}
