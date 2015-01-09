<?php

namespace VCR\Example;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\ClientErrorResponseException;

class ExampleHttpClient
{
    public function get($url)
    {
        $client = new Client();

        try {
            $request = $client->get($url);
            $response = $request->send();

            return json_decode($response->getBody(), true);
        } catch (ClientErrorResponseException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }

        return null;
    }
}


