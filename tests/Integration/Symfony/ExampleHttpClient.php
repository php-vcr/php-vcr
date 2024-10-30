<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExampleHttpClient
{
    /**
     * @var HttpClientInterface
     */
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create(['http_version' => '1.1']);
    }

    /**
     * Send a GET request to the specified URL.
     *
     * @param string $url The URL to send the request to.
     * @return array|null The decoded JSON response as an associative array, or null if a 404 error occurs.
     * @throws ClientExceptionInterface|TransportExceptionInterface|ServerExceptionInterface For other HTTP errors.
     */
    public function get(string $url): ?array
    {
        try {
            $response = $this->client->request('GET', $url);

            return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientExceptionInterface $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        } catch (\JsonException $e) {
            throw new \RuntimeException('Failed to decode JSON response: ' . $e->getMessage(), 0, $e);
        }

        return null;
    }

    /**
     * Send a POST request to the specified URL with a given body.
     *
     * @param string $url The URL to send the request to.
     * @param array|string $body The request body as an array or JSON string.
     * @return array|null The decoded JSON response as an associative array, or null if a 404 error occurs.
     * @throws ClientExceptionInterface|TransportExceptionInterface|ServerExceptionInterface For other HTTP errors.
     */
    public function post(string $url, array|string $body): ?array
    {
        try {
            $response = $this->client->request('POST', $url, [
                'body' => is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body,
            ]);

            return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ClientExceptionInterface $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        } catch (\JsonException $e) {
            throw new \RuntimeException('Failed to decode JSON response: ' . $e->getMessage(), 0, $e);
        }

        return null;
    }
}
