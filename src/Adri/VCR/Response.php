<?php

namespace Adri\PHPVCR;

class Response
{
    protected $response;

    public function __construct($statusCode, $headers = null, $body = null)
    {
        $this->response = new \Guzzle\Http\Message\Response($statusCode, $headers, $body);
    }

    public function getBody($asString = false)
    {
        return $this->response->getBody($asString);
    }

    public function toArray()
    {
        return array(
            'status'  => $this->response->getStatusCode(),
            'headers' => $this->response->getHeaders()->getAll(),
            'body'    => $this->response->getBody(true)
        );
    }

    public static function fromArray(array $response)
    {
        return new Response(
            $response['status'],
            $response['headers'],
            $response['body']
        );
    }
}
