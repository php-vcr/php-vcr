<?php
namespace VCR;

class Client
{
    protected $client;

    public function __construct()
    {
        $this->client = new \Guzzle\Http\Client();
    }

    public function getClient()
    {
        return $this->client;
    }

    public function send($request)
    {
        try {
            $response = $this->client->send($request);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }
        return new Response($response->getStatusCode(), $response->getHeaders(), $response->getBody());
    }
}
