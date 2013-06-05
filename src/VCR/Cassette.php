<?php

namespace VCR;

use \VCR\Storage\StorageInterface;

/**
 */
class Cassette
{
    protected $name;
    protected $config;
    protected $storage;
    protected $cassetteHandle;

    function __construct($name, Configuration $config, StorageInterface $storage)
    {
        $this->name = $name;
        $this->config = $config;
        $this->storage = $storage;
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

        $recording = array(
            'request'  => $request->toArray(),
            'response' => $response->toArray()
        );

        $this->storage->storeRecording($recording);
    }

    public function getName()
    {
        return $this->name;
    }

    protected function getRequestMatchers()
    {
        return $this->config->getRequestMatchers();
    }
}
