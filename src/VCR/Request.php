<?php

namespace VCR;

class Request extends \Guzzle\Http\Message\EntityEnclosingRequest
{
    public function matches(Request $request, array $requestMatchers)
    {
        foreach ($requestMatchers as $matcher) {
            if (!is_callable($matcher)) {
                throw new \BadFunctionCallException(
                    'Matcher could not be executed.' . print_r($matcher, true)
                );
            }

            if (call_user_func_array($matcher, array($this, $request)) === false) {
                return false;
            }
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
        return array_filter(array(
            'method'      => $this->getMethod(),
            'url'         => $this->getUrl(),
            'headers'     => $this->getHeaders(),
            'body'        => $this->getBody(),
            'post_files'  => (array) $this->getPostFiles(),
            'post_fields' => (array) $this->getPostFields()->toArray(),
        ));
    }

    public function getHeaders($asObjects = false)
    {
        if ($asObjects === true) {
            return $this->getHeaders($asObjects);
        }

        $headers = array();
        foreach (parent::getHeaders()->getAll() as $key => $header){
            $value = $header->toArray();
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

        if (isset($request['post_fields']) && is_array($request['post_fields']) && !empty($request['post_fields'])) {
            $requestObject->addPostFields($request['post_fields']);
        }

        if (isset($request['post_files']) && is_array($request['post_files']) && !empty($request['post_files'])) {
            $requestObject->addPostFiles($request['post_files']);
        }

        return $requestObject;
    }
}
