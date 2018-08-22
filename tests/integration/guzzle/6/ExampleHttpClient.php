<?php

namespace VCR\Example;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ExampleHttpClient
{
    public function get($url)
    {
        $client = new Client();

        try {
            $response = $client->get($url);
            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        return null;
    }

    public function post($url, $body)
    {
        $client = new Client();
        
        try {
            $response = $client->post($url, array('body' => $body));
            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        return null;
    }
}
