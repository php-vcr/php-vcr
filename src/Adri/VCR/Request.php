<?php

namespace Adri\PHPVCR;

class Request
{
    private $request;

    public function __construct($method = null, $url = null, $headers = array())
    {
        $this->request = new \Guzzle\Http\Message\Request($method, $url, $headers);
    }

    /**
     * Adapted from https://github.com/symfony/HttpFoundation/blob/master/RequestMatcher.php
     */
    public function matches(Request $request)
    {
        if ($this->request->getMethod() !== $request->getMethod()) {
            return false;
        }

        $requestHeaders = $request->getHeaders();
        foreach ($this->request->getHeaders() as $key => $pattern) {
            if (!preg_match('#'.str_replace('#', '\\#', $pattern[0]).'#', $requestHeaders[$key][0])) {
                return false;
            }
        }

        if (null !== $this->request->getPath()) {
            $path = str_replace('#', '\\#', $this->request->getPath());

            if (!preg_match('#'.$path.'#', rawurldecode($request->getPath()))) {
                return false;
            }
        }

        if (null !== $this->request->getHost()
           && !preg_match('#'.str_replace('#', '\\#', $this->request->getHost()).'#i', $request->getHost())) {
            return false;
        }

        return true;
    }

    public function send()
    {
        $response = $this->request->send();
        return new Response($response->getStatusCode(), $response->getHeaders(), $response->getBody());
    }

    public function setClient(Client $client)
    {
        $this->request->setClient($client->getClient());
    }

    public function setUrl($url)
    {
        $this->request->setUrl($url);
    }

    public function getMethod()
    {
        return $this->request->getMethod();
    }

    public function getHeaders()
    {
        return $this->request->getHeaders()->getAll();
    }

    public function getPath()
    {
        return $this->request->getPath();
    }

    public function getHost()
    {
        return $this->request->getHost();
    }

    public function toArray()
    {
        return array(
            'method'  => $this->request->getMethod(),
            'url'     => $this->request->getUrl(),
            'headers' => $this->request->getHeaders()->getAll(),
        );
    }

    public static function fromArray(array $request)
    {
        return new Request(
            $request['method'],
            $request['url'],
            $request['headers']
        );
    }
}
