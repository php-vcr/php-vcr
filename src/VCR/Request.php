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
    protected $headers = [];
    /**
     * @var string|null
     */
    protected $body;
    /**
     * @var array<int,array<string,string>>
     */
    protected $postFiles = [];
    /**
     * @var array<string,mixed>
     */
    protected $postFields = [];
    /**
     * @var array<int,mixed>
     */
    protected $curlOptions = [];

    /**
     * @param array<string,string> $headers
     */
    public function __construct(string $method, ?string $url, array $headers = [])
    {
        $this->method = $method;
        $this->headers = $headers;
        $this->setUrl($url);
    }

    /**
     * Returns true if specified request matches the current one
     * with specified request matcher callbacks.
     *
     * @param Request    $request         request to check if it matches the current one
     * @param callable[] $requestMatchers request matcher callbacks
     *
     * @throws \BadFunctionCallException if one of the specified request matchers is not callable
     *
     * @return bool true if specified request matches the current one
     */
    public function matches(self $request, array $requestMatchers): bool
    {
        foreach ($requestMatchers as $matcher) {
            if (!\is_callable($matcher)) {
                throw new \BadFunctionCallException('Matcher could not be executed. '.print_r($matcher, true));
            }

            if (false === \call_user_func_array($matcher, [$this, $request])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns an array representation of this request.
     *
     * @return array<string,mixed> array representation of this request
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'method' => $this->getMethod(),
                'url' => $this->getUrl(),
                'headers' => $this->getHeaders(),
                'body' => $this->getBody(),
                'post_files' => $this->getPostFiles(),
                'post_fields' => $this->getPostFields(),
            ]
        );
    }

    /**
     * Creates a new Request from a specified array.
     *
     * @param array<string,mixed> $request Request represented as an array. Allowed keys: "method", "url", "headers",
     *                                     "post_fields", "post_files", "body"
     *
     * @return Request a new Request from specified array
     */
    public static function fromArray(array $request): self
    {
        $requestObject = new self(
            $request['method'],
            $request['url'],
            isset($request['headers']) ? $request['headers'] : []
        );

        if (!empty($request['post_fields']) && \is_array($request['post_fields'])) {
            $requestObject->setPostFields($request['post_fields']);
        }

        if (!empty($request['post_files']) && \is_array($request['post_files'])) {
            foreach ($request['post_files'] as $file) {
                $requestObject->addPostFile($file);
            }
        }

        if (!empty($request['body'])) {
            $requestObject->setBody((string) $request['body']);
        }

        return $requestObject;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
        if (null !== $url && false === $this->hasHeader('Host')) {
            $this->setHeader('Host', $this->getHost());
        }
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getMethod(): string
    {
        if (null !== $this->getCurlOption(CURLOPT_CUSTOMREQUEST)) {
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

    public function getHeader(string $key): ?string
    {
        if (!isset($this->headers[$key])) {
            return null;
        }

        return $this->headers[$key];
    }

    public function hasHeader(string $key): bool
    {
        return \array_key_exists($key, $this->headers);
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getHost(): string
    {
        $url = $this->getUrl();
        Assertion::string($url);

        $host = parse_url($url, PHP_URL_HOST);

        if (null === $host || false === $host) {
            throw InvalidHostException::create($this->getUrl());
        }

        if ($port = parse_url($url, PHP_URL_PORT)) {
            $host .= ':'.$port;
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

    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    /**
     * Sets the authorization credentials as header.
     *
     * @param string $username username
     * @param string $password password
     */
    public function setAuthorization(string $username, string $password): void
    {
        $this->setHeader('Authorization', 'Basic '.base64_encode($username.':'.$password));
    }

    /**
     * @param array<int,mixed> $curlOptions
     */
    public function setCurlOptions(array $curlOptions): void
    {
        $this->curlOptions = $curlOptions;
    }

    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function removeHeader(string $key): void
    {
        unset($this->headers[$key]);
    }

    /**
     * @param mixed $value
     */
    public function setPostField(string $key, $value): void
    {
        $this->postFields[$key] = $value;
    }

    /**
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
