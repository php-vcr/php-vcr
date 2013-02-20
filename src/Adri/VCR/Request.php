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

        $requestHeaders = $request->getHeaders();
        foreach ($this->getHeaders() as $key => $pattern) {
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

        if (null !== $this->getPostFields()->toArray()
          && $this->getPostFields()->toArray() != $request->getPostFields()->toArray() ) {
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
            'method'      => $this->getMethod(),
            'url'         => $this->getUrl(),
            'headers'     => $this->getHeaders(),
            'body'        => $this->getBody(),
            'post_files'  => (array) $this->getPostFiles(),
            'post_fields' => (array) $this->getPostFields(),
        );
    }

    public function getHeaders($asObjects = false)
    {
        if ($asObjects === true) {
            return $this->getHeaders($asObjects);
        }

        $headers = array();
        foreach (parent::getHeaders()->getAll() as $key => $value) {
            $headers[$key] = $value[0];
        }
        return $headers;
    }

    public static function fromArray(array $request)
    {
        $requestObject = new Request(
            $request['method'],
            $request['url'],
            $request['headers']
        );

        if (isset($request['post_fields']) && is_array($request['post_fields'])) {
            $requestObject->addPostFields($request['post_fields']);
        }

        if (isset($request['post_files']) && is_array($request['post_files'])) {
            $requestObject->addPostFiles($request['post_files']);
        }

        return $requestObject;
    }
}
