<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\SymfonyHttpClient\Support;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client to interact with JSONPlaceholder API for testing Symfony HttpClient with VCR.
 */
class JsonPlaceholderClient
{
    private const BASE_URL = 'https://jsonplaceholder.typicode.com';

    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Get a single post by ID.
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function getPost(int $id): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL.'/posts/'.$id,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 30,
                ]
            );

            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                throw new \RuntimeException("HTTP request failed with code {$statusCode}");
            }

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Transport error: '.$e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new \RuntimeException('Request failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new post.
     *
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException
     */
    public function createPost(array $data): array
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                self::BASE_URL.'/posts',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $data,
                    'timeout' => 30,
                ]
            );

            if (201 !== $response->getStatusCode()) {
                throw new \RuntimeException("HTTP request failed with code {$response->getStatusCode()}");
            }

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Transport error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing post.
     *
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException
     */
    public function updatePost(int $id, array $data): array
    {
        try {
            $response = $this->httpClient->request(
                'PUT',
                self::BASE_URL.'/posts/'.$id,
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $data,
                    'timeout' => 30,
                ]
            );

            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException("HTTP request failed with code {$response->getStatusCode()}");
            }

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Transport error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a post.
     *
     * @throws \RuntimeException
     */
    public function deletePost(int $id): bool
    {
        try {
            $response = $this->httpClient->request(
                'DELETE',
                self::BASE_URL.'/posts/'.$id,
                [
                    'timeout' => 30,
                ]
            );

            return 200 === $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Transport error: '.$e->getMessage(), 0, $e);
        }
    }
}
