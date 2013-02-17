<?php

namespace Adri\VCR;

class Request extends \Guzzle\Http\Message\EntityEnclosingRequest
{

    /**
     * Adapted from https://github.com/symfony/HttpFoundation/blob/master/RequestMatcher.php
     */
    public function matches(Request $request)
    {
        if ($this->getMethod() !== $request->getMethod()) {
            return false;
        }

        $requestHeaders = $request->getHeaders()->getAll();
        foreach ($this->getHeaders()->getAll() as $key => $pattern) {
            if (!preg_match('#'.str_replace('#', '\\#', $pattern[0]).'#', $requestHeaders[$key][0])) {
                return false;
            }
        }

        if (null !== $this->getPath()) {
            $path = str_replace('#', '\\#', $this->getPath());

            if (!preg_match('#'.$path.'#', rawurldecode($request->getPath()))) {
                return false;
            }
        }

        if (null !== $this->getHost()
           && !preg_match('#'.str_replace('#', '\\#', $this->getHost()).'#i', $request->getHost())) {
            return false;
        }

        return true;
    }

    public function send()
    {
        $response = parent::send();
        return new Response($response->getStatusCode(), $response->getHeaders(), $response->getBody());
    }

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function toArray()
    {
        return array(
            'method'  => $this->getMethod(),
            'url'     => $this->getUrl(),
            'headers' => $this->getHeaders()->getAll(),
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
