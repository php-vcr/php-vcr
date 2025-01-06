<?php

declare(strict_types=1);

namespace VCR;

use VCR\Util\Assertion;

/**
 * Encapsulates a HTTP response.
 */
class Response
{
    protected int $statusCode;

    protected string $statusMessage = '';

    /**
     * @var array<string,string>
     */
    protected array $headers = [];
    protected ?string $body;
    /**
     * @var array<string,mixed>
     */
    protected array $curlInfo = [];

    protected mixed $httpVersion = null;

    /**
     * @param string|array<string, string> $status
     * @param array<string,string>         $headers
     * @param array<string,mixed>          $curlInfo
     */
    final public function __construct($status, array $headers = [], ?string $body = null, array $curlInfo = [])
    {
        $this->setStatus($status);
        $this->headers = $headers;
        $this->body = $body;
        $this->curlInfo = $curlInfo;
    }

    /**
     * Returns an array representation of this Response.
     *
     * @return array<string,mixed> array representation of this Request
     */
    public function toArray(): array
    {
        $body = $this->getBody();
        // Base64 encode when binary
        if (
            null !== $this->getContentType()
            && (
                str_contains($this->getContentType(), 'application/x-gzip')
                || 'binary' == $this->getHeader('Content-Transfer-Encoding')
            )
        ) {
            $body = base64_encode($body);
        }

        return array_filter(
            [
                'status' => [
                    'code' => $this->statusCode,
                    'message' => $this->statusMessage,
                ],
                'headers' => $this->getHeaders(),
                'body' => $body,
                'curl_info' => $this->curlInfo,
            ]
        );
    }

    /**
     * Creates a new Response from a specified array.
     *
     * @param array<string,mixed> $response array representation of a Response
     *
     * @return Response A new Response from a specified array
     */
    public static function fromArray(array $response): self
    {
        $body = $response['body'] ?? null;

        $gzip = isset($response['headers']['Content-Type'])
            && str_contains($response['headers']['Content-Type'], 'application/x-gzip');

        $binary = isset($response['headers']['Content-Transfer-Encoding'])
            && 'binary' == $response['headers']['Content-Transfer-Encoding'];

        // Base64 decode when binary
        if ($gzip || $binary) {
            $body = base64_decode($response['body']);
        }

        return new static(
            $response['status'] ?? 200,
            $response['headers'] ?? [],
            $body,
            $response['curl_info'] ?? []
        );
    }

    public function getBody(): string
    {
        return $this->body ?: '';
    }

    /**
     * @return array<string,mixed>|mixed|null
     */
    public function getCurlInfo(?string $option = null): mixed
    {
        if (empty($option)) {
            return $this->curlInfo;
        }
        if (!isset($this->curlInfo[$option])) {
            return null;
        }

        return $this->curlInfo[$option];
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContentType(): ?string
    {
        return $this->getHeader('Content-Type');
    }

    public function getHeader(string $key): ?string
    {
        if (!isset($this->headers[$key])) {
            return null;
        }

        return $this->headers[$key];
    }

    public function getHttpVersion(): mixed
    {
        return $this->httpVersion;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    /**
     * @param string|array<string,mixed> $status
     */
    protected function setStatus($status): void
    {
        if (\is_array($status)) {
            $this->statusCode = (int) $status['code'];
            $this->statusMessage = $status['message'];
            if (!empty($status['http_version'])) {
                $this->httpVersion = $status['http_version'];
            }
        } else {
            Assertion::numeric($status, 'Response status must be either an array or a number.');
            $this->statusCode = (int) $status;
        }
    }
}
