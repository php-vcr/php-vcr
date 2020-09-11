<?php

namespace VCR;

use VCR\Util\Assertion;

/**
 * Encapsulates a HTTP response.
 */
class Response
{
    /**
     * @var array<string, string|null>
     */
    protected $status = [
        'code' => null,
        'message' => '',
    ];

    /**
     * @var array<string,string>
     */
    protected $headers = [];
    /**
     * @var string|null
     */
    protected $body;
    /**
     * @var array<string,mixed>
     */
    protected $curlInfo = [];

    /**
     * @var mixed
     */
    protected $httpVersion;

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
        if (false !== strpos($this->getContentType(), 'application/x-gzip')
            || 'binary' == $this->getHeader('Content-Transfer-Encoding')
        ) {
            $body = base64_encode($body);
        }

        return array_filter(
            [
                'status' => $this->status,
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
        $body = isset($response['body']) ? $response['body'] : null;

        $gzip = isset($response['headers']['Content-Type'])
            && false !== strpos($response['headers']['Content-Type'], 'application/x-gzip');

        $binary = isset($response['headers']['Content-Transfer-Encoding'])
            && 'binary' == $response['headers']['Content-Transfer-Encoding'];

        // Base64 decode when binary
        if ($gzip || $binary) {
            $body = base64_decode($response['body']);
        }

        return new static(
            isset($response['status']) ? $response['status'] : 200,
            isset($response['headers']) ? $response['headers'] : [],
            $body,
            isset($response['curl_info']) ? $response['curl_info'] : []
        );
    }

    public function getBody(): string
    {
        return $this->body ?: '';
    }

    /**
     * @return array<string,mixed>|mixed|null
     */
    public function getCurlInfo(?string $option = null)
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

    public function getStatusCode(): string
    {
        return $this->status['code'];
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

    /**
     * @return mixed
     */
    public function getHttpVersion()
    {
        return $this->httpVersion;
    }

    public function getStatusMessage(): string
    {
        return $this->status['message'];
    }

    /**
     * @param string|array<string,mixed> $status
     */
    protected function setStatus($status): void
    {
        if (\is_array($status)) {
            $this->status = $status;
            if (!empty($status['http_version'])) {
                $this->httpVersion = $status['http_version'];
            }
        } else {
            Assertion::numeric($status, 'Response status must be either an array or a number.');
            $this->status['code'] = $status;
        }
    }
}
