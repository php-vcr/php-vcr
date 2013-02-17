<?php

namespace Adri\VCR;

use \Symfony\Component\HttpFoundation\RequestMatcher;

class Cassette
{
    protected $name;
    protected $config;
    protected $hasReadFromDisk = false;
    protected $httpInteractions = array();

    function __construct($name, Configuration $config)
    {
        $this->name = $name;
        $this->config = $config;
    }

    public function hasResponse(Request $request)
    {
        if ($this->hasReadFromDisk === false) {
            $this->readFromDisk();
        }
        return $this->getResponse($request) !== null;
    }

    public function playback(Request $request)
    {
        $response = $this->getResponse($request);
        return $response;
    }

    public function record(Request $request, $response)
    {
        $this->httpInteractions[] = array('request' => $request, 'response' => $response);
        $this->writeToDisk();
    }

    /**
     * Writes all http interactions to disk.
     * @return void
     */
    public function writeToDisk()
    {
        $recordings = array();
        foreach ($this->httpInteractions as $interaction) {
            $recordings[] = array(
                'request'  => $interaction['request']->toArray(),
                'response' => $interaction['response']->toArray()
            );
        }

        file_put_contents($this->getCassettePath(), json_encode($recordings));
    }

    /**
     * Reads all http interactions from disk.
     * @return void
     */
    public function readFromDisk()
    {
        if (!file_exists($this->getCassettePath())) {
            return;
        }

        $recordings = json_decode(file_get_contents($this->getCassettePath()), true);

        foreach ($recordings as $recording) {
            $this->httpInteractions[] = array(
                'request'  => Request::fromArray($recording['request']),
                'response' => Response::fromArray($recording['response'])
            );
        }
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns a response for given request or null if not found.
     *
     * @param  Request $request Request.
     * @return string Response for specified request.
     */
    protected function getResponse(Request $request)
    {
        foreach ($this->httpInteractions as $httpInteraction) {
            if ($request->matches($httpInteraction['request'])) {
                return $httpInteraction['response'];
            }
        }

        return null;
    }

    protected function getCassettePath()
    {
        return $this->config->getCassettePath() . DIRECTORY_SEPARATOR . $this->name;
    }
}
