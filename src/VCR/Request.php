<?php

namespace VCR;

use Assert\Assertion;
use VCR\Exceptions\InvalidHostException;

/**
 * Encapsulates a HTTP request.
 */
class Request
{
    /**
     * @var string
     */
    protected $method;
    /**
     * @var string|null
     */
    protected $url;
    /**
     * @var array<string,string>
     */
    protected $headers = array();
    /**
     * @var string|null
     */
    protected $body;
    /**
     * @var array<int,array<string,string>>
     */
    protected $postFiles = array();
    /**
     * @var array<string,mixed>
     */
    protected $postFields = array();
    /**
     * @var array<int,mixed>
     */
    protected $curlOptions = array();

    /**
     * @param string $method
     * @param string|null $url
     * @param array<string,string> $headers
     */
    public function __construct(string $method, ?string $url, array $headers = array())
    {
        $this->method = $method;
        $this->headers = $headers;
        $this->setUrl($url);
    }

    /**
     * Returns an array representation of this request.
     *
     * @return array<string,mixed> Array representation of this request.
     */
    public function toArray(): array
    {
        return array_filter(
            array(
                'method' => $this->getMethod(),
                'url' => $this->getUrl(),
                'headers' => $this->getHeaders(),
                'body' => $this->getBody(),
                'post_files' => $this->getPostFiles(),
                'post_fields' => $this->getPostFields(),
            )
        );
    }

    /**
     * Creates a new Request from a specified array.
     *
     * @param  array<string,mixed> $request Request represented as an array. Allowed keys: "method", "url", "headers",
     *                                      "post_fields", "post_files", "body"
     *
     * @return Request A new Request from specified array.
     */
    public static function fromArray(array $request): Request
    {
        $requestObject = new Request(
            $request['method'],
            $request['url'],
            isset($request['headers']) ? $request['headers'] : array()
        );

        if (!empty($request['post_fields']) && is_array($request['post_fields'])) {
            $requestObject->setPostFields($request['post_fields']);
        }

        if (!empty($request['post_files']) && is_array($request['post_files'])) {
            foreach ($request['post_files'] as $file) {
                $requestObject->addPostFile($file);
            }
        }

        if (!empty($request['body'])) {
            $requestObject->setBody((string)$request['body']);
        }

        return $requestObject;
    }

    /**
     * @param string|null $url
     */
    public function setUrl(?string $url): void
    {
        $this->url = $url;
        if ($url !== null && $this->hasHeader('Host') === false) {
            $this->setHeader('Host', $this->getHost());
        }
    }

    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        if ($this->getCurlOption(CURLOPT_CUSTOMREQUEST) !== null) {
            return $this->getCurlOption(CURLOPT_CUSTOMREQUEST);
        }

        return $this->method;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getHeader(string $key): ?string
    {
        if (!isset($this->headers[$key])) {
            return null;
        }

        return $this->headers[$key];
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function hasHeader(string $key): bool
    {
        return array_key_exists($key, $this->headers);
    }

    /**
     * @return array<string,mixed>
     */
    public function getPostFields(): array
    {
        return $this->postFields;
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function getPostFiles(): array
    {
        return $this->postFiles;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        $url = $this->getUrl();
        Assertion::string($url);

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false) {
            throw InvalidHostException::create($this->getUrl());
        }

        if ($port = parse_url($url, PHP_URL_PORT)) {
            $host .= ':' . $port;
        }

        return $host;
    }

    /**
     * @return string
     */
    public function getPath(): ?string
    {
        $url = $this->getUrl();
        Assertion::string($url);
        $path = parse_url($url, PHP_URL_PATH);
        Assertion::notSame($path, false);
        return $path;
    }

    /**
     * @return string|null
     */
    public function getQuery(): ?string
    {
        $url = $this->getUrl();
        Assertion::string($url);
        $query = parse_url($url, PHP_URL_QUERY);
        Assertion::notSame($query, false);
        return $query;
    }

    /**
     * @return array<int,mixed>
     */
    public function getCurlOptions(): array
    {
        return $this->curlOptions;
    }

    /**
     * @param int $key
     * @return mixed
     */
    public function getCurlOption(int $key)
    {
        if (empty($this->curlOptions[$key])) {
            return null;
        }

        return $this->curlOptions[$key];
    }

    /**
     * Sets the request method.
     *
     * @param string $method HTTP request method like GET, POST, PUT, ...
     */
    public function setMethod(string $method): void
    {
        $this->method = strtoupper($method);
    }

    /**
     * @param array<string,mixed> $post_fields
     */
    public function setPostFields(array $post_fields): void
    {
        $this->postFields = $post_fields;
    }

    /**
     * @param array<int,array<string,string>> $post_files
     */
    public function setPostFiles(array $post_files): void
    {
        $this->postFiles = $post_files;
    }

    /**
     * @param string|null $body
     */
    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    /**
     * Sets the authorization credentials as header.
     *
     * @param string $username Username.
     * @param string $password Password.
     */
    public function setAuthorization(string $username, string $password): void
    {
        $this->setHeader('Authorization', 'Basic ' . base64_encode($username . ':' . $password));
    }

    /**
     * @param array<int,mixed> $curlOptions
     */
    public function setCurlOptions(array $curlOptions): void
    {
        $this->curlOptions = $curlOptions;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    /**
     * @param string $key
     */
    public function removeHeader(string $key): void
    {
        unset($this->headers[$key]);
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function setPostField(string $key, $value): void
    {
        $this->postFields[$key] = $value;
    }

    /**
     * @param int $key
     * @param mixed $value
     */
    public function setCurlOption(int $key, $value): void
    {
        $this->curlOptions[$key] = $value;
    }

    /**
     * @param array<string,string> $file An array with the keys "fieldName", "contentType", "filename" and "postname"
     */
    public function addPostFile(array $file): void
    {
        $this->postFiles[] = $file;
    }
}
