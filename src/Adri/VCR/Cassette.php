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
    protected $recordings;
    protected $cassetteHandle;

    function __construct($name, Configuration $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->readFromDisk();
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
        foreach ($this->recordings as $recording) {
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

        fseek($this->cassetteHandle, -1, SEEK_END);
        if (filesize($this->getCassettePath()) > 2) {
            fwrite($this->cassetteHandle, ',');
        }
        fwrite($this->cassetteHandle, json_encode($recording) . ']');
        fflush($this->cassetteHandle);
    }

    /**
     * Reads all http interactions from disk.
     * @return void
     * @todo move o storage class
     */
    public function readFromDisk()
    {
        if (!file_exists($this->getCassettePath())) {
            file_put_contents($this->getCassettePath(), '[]');
        }

        $this->cassetteHandle = fopen($this->getCassettePath(), 'r+');
        $this->recordings = new Storage\Json($this->cassetteHandle);
    }

    public function getName()
    {
        return $this->name;
    }

    public function __destruct()
    {
        fclose($this->cassetteHandle);
    }

    protected function getCassettePath()
    {
        return $this->config->getCassettePath() . DIRECTORY_SEPARATOR . $this->name;
    }

    public function getRequestMatchers()
    {
        return $this->config->getRequestMatchers();
    }
}
