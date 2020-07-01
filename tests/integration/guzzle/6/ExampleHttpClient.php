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
            if (404 !== $e->getCode()) {
                throw $e;
            }
        }

        return null;
    }

    public function post($url, $body)
    {
        $client = new Client();

        try {
            $response = $client->post($url, ['body' => $body]);

            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            if (404 !== $e->getCode()) {
                throw $e;
            }
        }

        return null;
    }
}
