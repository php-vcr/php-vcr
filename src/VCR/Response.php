<?php

namespace VCR;

use VCR\Util\Assertion;

/**
 * Encapsulates a HTTP response.
 */
class Response
{
    /**
     * @var array
     */
    protected $status = array(
        'code' => null,
        'message' => ''
    );

    /**
     * @var array
     */
    protected $headers = array();
    /**
     * @var string
     */
    protected $body;
    /**
     * @var array
     */
    protected $curlInfo = array();

    protected $httpVersion;

    /**
     * @param string|array $status
     * @param array $headers
     * @param string $body
     * @param array $curlInfo
     */
    final public function __construct($status, array $headers = array(), $body = null, array $curlInfo = array())
    {
        $this->setStatus($status);
        $this->headers = $headers;
        $this->body = $body;
        $this->curlInfo = $curlInfo;
    }

    /**
     * Returns an array representation of this Response.
     *
     * @return array Array representation of this Request.
     */
    public function toArray()
    {
        $body = $this->getBody();
        if (!empty($body) && self::isBinaryResponse($this->getHeaders())) {
            $body = base64_encode($body);
        }

        return array_filter(
            array(
                'status'    => $this->status,
                'headers'   => $this->getHeaders(),
                'body'      => $body
            )
        );
    }

    /**
     * Creates a new Response from a specified array.
     *
     * @param  array  $response Array representation of a Response.
     * @return Response A new Response from a specified array
     */
    public static function fromArray(array $response)
    {
        $body = isset($response['body']) ? $response['body'] : null;
        if (!empty($body) && self::isBinaryResponse($response['headers'])) {
            $body = base64_decode($response['body']);
        }

        return new static(
            isset($response['status']) ? $response['status'] : 200,
            isset($response['headers']) ? $response['headers'] : array(),
            $body
        );
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return array
     */
    public function getCurlInfo($option = null)
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
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getStatusCode()
    {
        return $this->status['code'];
    }

    public function getContentType()
    {
        return $this->getHeader('Content-Type');
    }

    public function getHeader($key)
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

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->status['message'];
    }

    /**
     * @param string|array $status
     */
    protected function setStatus($status)
    {
        if (is_array($status)) {
            $this->status = $status;
            if (!empty($status['http_version'])) {
                $this->httpVersion = $status['http_version'];
            }
        } else {
            Assertion::numeric($status, 'Response status must be either an array or a number.');
            $this->status['code'] = $status;
        }
    }

    /**
     * @param array $headers
     * @return bool
     */
    public static function isBinaryResponse(array $headers)
    {
        if (empty($headers)) {
            return true;
        }

        foreach (['Content-Encoding', 'Transfer-Encoding', 'Content-Transfer-Encoding'] as $header) {
            if (isset($headers[$header])) {
                return true;
            }
        }

        if (!isset($headers['Content-Type'])) {
            return true;
        }

        $contentType = $headers['Content-Type'];
        $textContentTypes = ['json', 'xml', 'text', 'css', 'html', 'plain', 'javascript'];
        foreach ($textContentTypes as $textContentType) {
            if (strpos($contentType, $textContentType) !== false) {
                return false;
            }
        }

        return true;
    }
}
