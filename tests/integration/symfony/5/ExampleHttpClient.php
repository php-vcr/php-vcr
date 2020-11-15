<?php

namespace VCR\Example;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExampleHttpClient
{
    /**
     * @var HttpClientInterface
     */
    private $client;

    public function __construct()
    {
        $this->client = HttpClient::create(['http_version' => '1.1']);
    }

    public function get($url)
    {
        try {
            $response = $this->client->request('GET', $url);

            return json_decode($response->getContent(), true);
        } catch (ClientExceptionInterface $e) {
            if (404 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }
        }

        return null;
    }

    public function post($url, $body)
    {
        try {
            $response = $this->client->request('POST', $url, ['body' => $body]);

            return json_decode($response->getContent(), true);
        } catch (ClientExceptionInterface $e) {
            if (404 !== $e->getResponse()->getStatusCode()) {
                throw $e;
            }
        }

        return null;
    }
}
