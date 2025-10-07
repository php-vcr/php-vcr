<?php

declare(strict_types=1);

namespace VCR;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use VCR\Http\NormalizedResponseWrapper;

/**
 * VCR-aware Symfony HttpClient wrapper.
 *
 * This client intercepts HTTP requests at the Symfony HttpClient level
 * (instead of at the cURL level) and plays back responses from VCR cassettes.
 *
 * Benefits over cURL hooks:
 * - Avoids timing issues with Symfony's async response handling
 * - Properly handles gzip decompression
 * - Works with all Symfony HttpClient implementations
 *
 * Usage:
 * $client = new VCRHttpClient(new CurlHttpClient());
 */
class VCRHttpClient implements HttpClientInterface
{
    private HttpClientInterface $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        // If VCR is active, intercept the request.
        if ($this->isVCRActive()) {
            return $this->handleVCRRequest($method, $url, $options);
        }

        // Otherwise, pass through to the real client.
        // Wrap the response to normalize exceptions (Symfony may throw them lazily or immediately).
        try {
            $response = $this->client->request($method, $url, $options);
        } catch (\Symfony\Component\HttpClient\Exception\TransportException $e) {
            // Some clients (like MockHttpClient) throw immediately in request()
            throw $this->normalizeException($e);
        }

        // Wrap response to normalize exceptions when they're thrown lazily (like CurlHttpClient)
        return new NormalizedResponseWrapper($response, [$this, 'normalizeException']);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        $responsesArray = [];
        $responsesIterable = is_iterable($responses) ? $responses : [$responses];
        foreach ($responsesIterable as $response) {
            $responsesArray[] = $response;
        }

        $allMockResponses = true;
        foreach ($responsesArray as $response) {
            if (!($response instanceof MockResponse)) {
                $allMockResponses = false;
                break;
            }
        }

        // If all responses are MockResponse (from VCR cassettes), they're already complete.
        // We create a simple streaming generator that yields each response.
        if ($allMockResponses) {
            return new class($responsesArray) implements ResponseStreamInterface {
                /** @var array<ResponseInterface> */
                private array $responses;
                private int $index = 0;

                /**
                 * @param array<ResponseInterface> $responses
                 */
                public function __construct(array $responses)
                {
                    $this->responses = array_values($responses); // Re-index
                }

                public function key(): ResponseInterface
                {
                    return $this->responses[$this->index];
                }

                public function current(): ChunkInterface
                {
                    // Return a "last" chunk to signal completion for this response
                    return new class implements ChunkInterface {
                        public function isTimeout(): bool
                        {
                            return false;
                        }

                        public function isFirst(): bool
                        {
                            return false;
                        }

                        public function isLast(): bool
                        {
                            return true;
                        }

                        public function getContent(): string
                        {
                            return '';
                        }

                        public function getOffset(): int
                        {
                            return 0;
                        }

                        public function getError(): ?string
                        {
                            return null;
                        }

                        /**
                         * @return array<int, string>|null
                         */
                        public function getInformationalStatus(): ?array
                        {
                            return null;
                        }

                        public function didThrow(): bool
                        {
                            return false;
                        }
                    };
                }

                public function next(): void
                {
                    ++$this->index;
                }

                public function rewind(): void
                {
                    $this->index = 0;
                }

                public function valid(): bool
                {
                    return $this->index < \count($this->responses);
                }
            };
        }

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

    /**
     * Check if VCR is active and has a cassette inserted.
     */
    private function isVCRActive(): bool
    {
        try {
            $videorecorder = VCRFactory::get(Videorecorder::class);

            if (!$videorecorder || !($videorecorder instanceof Videorecorder)) {
                return false;
            }

            return $videorecorder->hasCassette();
        } catch (\BadMethodCallException $e) {
            // VCR not initialized or no cassette
            // We intentionally swallow these exceptions to gracefully handle VCR absence
            return false;
        }
    }

    /**
     * Handle request through VCR.
     *
     * @param array<string, mixed> $options
     */
    private function handleVCRRequest(string $method, string $url, array $options): ResponseInterface
    {
        // Store original URL for error messages (before query params are added)
        $originalUrl = $url;

        if (isset($options['query'])) {
            $url .= (!str_contains($url, '?') ? '?' : '&').http_build_query($options['query']);
        }

        $headers = $this->normalizeHeaders($options['headers'] ?? []);
        $request = new Request($method, $url, $headers);

        if (isset($options['body'])) {
            $body = $options['body'];
            // Convert array body to URL-encoded string for form data
            if (\is_array($body)) {
                $body = http_build_query($body);
                if (!isset($headers['Content-Type'])) {
                    $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
                }
            }
            if (\is_string($body)) {
                $request->setBody($body);
            }
        }

        if (isset($options['json'])) {
            $jsonBody = json_encode($options['json']);
            if (\is_string($jsonBody)) {
                $request->setBody($jsonBody);
                $request->setHeader('Content-Type', 'application/json');
            }
        }

        if (isset($options['auth_basic'])) {
            $auth = $options['auth_basic'];
            if (\is_array($auth) && \count($auth) >= 2) {
                $request->setHeader('Authorization', 'Basic '.base64_encode($auth[0].':'.$auth[1]));
            }
        }

        try {
            $videorecorder = VCRFactory::get(Videorecorder::class);
        } catch (\BadMethodCallException $e) {
            // VCR not initialized, fall back to real client
            // We intentionally swallow these exceptions to gracefully handle VCR absence
            return $this->client->request($method, $url, $options);
        }

        if (!$videorecorder || !($videorecorder instanceof Videorecorder)) {
            return $this->client->request($method, $url, $options);
        }

        try {
            $response = $videorecorder->handleRequest($request);
        } catch (\Exception $e) {
            // Convert any exception to Symfony TransportException for consistency
            $message = $e->getMessage();

            // Ensure the error message matches Symfony's format
            // Symfony doesn't add trailing slash to URLs in error messages
            // Use regex to remove trailing slash from any quoted URL
            $message = preg_replace('#"(https?://[^"]+)/"#', '"$1"', $message) ?? $message;

            // If message doesn't already have the URL formatted, add it
            if (!str_contains($message, ' for "')) {
                $message .= \sprintf(' for "%s".', rtrim($originalUrl, '/'));
            }

            throw new \Symfony\Component\HttpClient\Exception\TransportException($message, 0, $e);
        }

        return $this->createMockResponse($response, $method, $url, $options);
    }

    /**
     * Normalize exception by removing trailing slashes from URLs.
     *
     * Public so it can be called from the anonymous Response wrapper class.
     */
    public function normalizeException(\Symfony\Component\HttpClient\Exception\TransportException $e): \Symfony\Component\HttpClient\Exception\TransportException
    {
        $message = $e->getMessage();
        $message = preg_replace('#"(https?://[^"]+)/"#', '"$1"', $message) ?? $message;

        return new \Symfony\Component\HttpClient\Exception\TransportException($message, 0, $e);
    }

    /**
     * Normalize headers to VCR format.
     *
     * @param array<int|string, string|string[]> $headers
     *
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (\is_int($key)) {
                // Header in format "Name: Value"
                if (\is_string($value)) {
                    $parts = explode(':', $value, 2);
                    if (2 === \count($parts)) {
                        $normalized[trim($parts[0])] = trim($parts[1]);
                    }
                }
            } else {
                // Header in format "Name" => "Value" or "Name" => ["Value1", "Value2"]
                if (\is_array($value)) {
                    $normalized[$key] = implode(', ', $value);
                } elseif (\is_string($value)) {
                    $normalized[$key] = $value;
                }
            }
        }

        return $normalized;
    }

    /**
     * Create a Symfony MockResponse from a VCR Response.
     *
     * @param array<string, mixed> $options
     */
    private function createMockResponse(Response $vcrResponse, string $method, string $url, array $options): ResponseInterface
    {
        $body = $vcrResponse->getBody();
        $headers = $vcrResponse->getHeaders();

        // Handle gzip decompression for Symfony HttpClient compatibility
        // Symfony's MockResponse doesn't handle gzip decompression automatically like real HttpClients do
        // We decompress here and remove the content-encoding header so MockResponse receives plain content
        $contentEncoding = $headers['content-encoding'] ?? $headers['Content-Encoding'] ?? null;
        if ('gzip' === $contentEncoding && \function_exists('gzdecode')) {
            $decompressed = @gzdecode($body);
            if (false !== $decompressed) {
                $body = $decompressed;
                // Remove content-encoding header so Symfony doesn't try to decompress again
                unset($headers['content-encoding'], $headers['Content-Encoding']);
                // Update content-length to match decompressed size
                $headers['content-length'] = (string) \strlen($body);
                $headers['Content-Length'] = (string) \strlen($body);
            }
        }

        $responseHeaders = [];
        foreach ($headers as $name => $value) {
            $responseHeaders[] = $name.': '.$value;
        }

        // Create MockHttpClient with response factory callback
        // This ensures MockResponse is properly initialized by MockHttpClient
        $mockClient = new MockHttpClient(fn () => new MockResponse($body, [
            'http_code' => $vcrResponse->getStatusCode(),
            'response_headers' => $responseHeaders,
        ]));

        return $mockClient->request($method, $url, $options);
    }

    /**
     * Sets a logger instance for the wrapped client.
     *
     * Symfony's DI container calls this method to inject a logger.
     * We pass it through to the wrapped client if it supports logging.
     */
    public function setLogger(object $logger): void
    {
        if (method_exists($this->client, 'setLogger')) {
            $this->client->setLogger($logger);
        }
    }
}
