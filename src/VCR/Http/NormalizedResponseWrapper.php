<?php

declare(strict_types=1);

namespace VCR\Http;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Wrapper that normalizes TransportException messages from Symfony HttpClient.
 *
 * This wrapper catches lazy exceptions (thrown by CurlHttpClient when accessing
 * response data) and normalizes them to remove trailing slashes from URLs.
 */
class NormalizedResponseWrapper implements ResponseInterface
{
    private ResponseInterface $response;
    /** @var callable */
    private $normalizer;

    /**
     * @param ResponseInterface $response   Original response to wrap
     * @param callable          $normalizer Callable that normalizes TransportException
     */
    public function __construct(ResponseInterface $response, callable $normalizer)
    {
        $this->response = $response;
        $this->normalizer = $normalizer;
    }

    public function getStatusCode(): int
    {
        try {
            return $this->response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw \call_user_func($this->normalizer, $e);
        }
    }

    public function getHeaders(bool $throw = true): array
    {
        try {
            return $this->response->getHeaders($throw);
        } catch (TransportExceptionInterface $e) {
            throw \call_user_func($this->normalizer, $e);
        }
    }

    public function getContent(bool $throw = true): string
    {
        try {
            return $this->response->getContent($throw);
        } catch (TransportExceptionInterface $e) {
            throw \call_user_func($this->normalizer, $e);
        }
    }

    /**
     * @return array<string|int, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        try {
            return $this->response->toArray($throw);
        } catch (TransportExceptionInterface $e) {
            throw \call_user_func($this->normalizer, $e);
        }
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }
}
