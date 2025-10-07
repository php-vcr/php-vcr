<?php

declare(strict_types=1);

namespace VCR;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * VCR-compatible replacement for NativeHttpClient.
 *
 * This class acts as a drop-in replacement for Symfony's NativeHttpClient
 * when using php-vcr. It uses CurlHttpClient internally, which is fully
 * compatible with VCR's curl hook system.
 *
 * Usage:
 *   Instead of:  new NativeHttpClient($options)
 *   Use:         new VCRNativeHttpClient($options)
 *
 * Why this is needed:
 * - NativeHttpClient uses PHP streams which have fundamental limitations with VCR
 * - PHP's stream wrappers cannot override stream_get_meta_data() behavior
 * - This causes TypeErrors when Symfony tries to read response headers
 *
 * This proxy provides the same API as NativeHttpClient but uses CurlHttpClient
 * under the hood, which works perfectly with VCR.
 */
class VCRNativeHttpClient implements HttpClientInterface
{
    private HttpClientInterface $client;

    /**
     * @param array<string,mixed> $defaultOptions     Default request options
     * @param int                 $maxHostConnections Maximum number of connections per host
     * @param int                 $maxPendingPushes   Maximum number of pending HTTP/2 pushes
     */
    public function __construct(array $defaultOptions = [], int $maxHostConnections = 6, int $maxPendingPushes = 50)
    {
        // Use CurlHttpClient with the same options
        // CurlHttpClient is fully compatible with VCR
        $this->client = new CurlHttpClient($defaultOptions, $maxHostConnections, $maxPendingPushes);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }
}
