<?php
namespace Adri\VCR;

class Client
{

    function __construct()
    {
        $this->client = new \Guzzle\Http\Client();
    }

    public function getClient()
    {
        return $this->client;
    }

    public function send($request)
    {
        $response = $this->client->send($request);
        return new Response($response->getStatusCode(), $response->getHeaders(), $response->getBody());
    }
}