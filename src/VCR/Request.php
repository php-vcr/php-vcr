<?php

namespace VCR;

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
     * @var string
     */
    protected $url;
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
    protected $postFiles = array();
    /**
     * @var array
     */
    protected $postFields = array();
    /**
     * @var array
     */
    protected $curlOptions = array();

    /**
     * @param string $method
     * @param string $url
     * @param array $headers
     */
    function __construct($method, $url, array $headers = array())
    {
        $this->method = $method;
        $this->headers = $headers;
        $this->setUrl($url);
    }

    /**
     * Returns true if specified request matches the current one
     * with specified request matcher callbacks.
     *
     * @param  Request $request Request to check if it matches the current one.
     * @param  \callable[] $requestMatchers Request matcher callbacks.
     *
     * @throws \BadFunctionCallException If one of the specified request matchers is not callable.
     * @return boolean True if specified request matches the current one.
     */
    public function matches(Request $request, array $requestMatchers)
    {
        foreach ($requestMatchers as $matcher) {
            if (!is_callable($matcher)) {
                throw new \BadFunctionCallException(
                    'Matcher could not be executed. ' . print_r($matcher, true)
                );
            }

            if (call_user_func_array($matcher, array($this, $request)) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns an array representation of this request.
     *
     * @return array Array representation of this request.
     */
    public function toArray()
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
     * @param  array $request Request represented as an array.
     *
     * @return Request A new Request from specified array.
     */
    public static function fromArray(array $request)
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
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
        $this->setHeader('Host', $this->getHost());
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getHeader($key)
    {
        return $this->headers[$key];
    }

    /**
     * @return array
     */
    public function getPostFields()
    {
        return $this->postFields;
    }

    /**
     * @return array
     */
    public function getPostFiles()
    {
        return $this->postFiles;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return mixed
     */
    public function getHost()
    {
        $host = parse_url($this->getUrl(), PHP_URL_HOST);

        if ($port = parse_url($this->getUrl(), PHP_URL_PORT)) {
          $host .= ':' . $port;
        }

        return $host;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return parse_url($this->getUrl(), PHP_URL_PATH);
    }

    /**
     * @return mixed
     */
    public function getQuery()
    {
        return parse_url($this->getUrl(), PHP_URL_QUERY);
    }

    /**
     * @return array
     */
    public function getCurlOptions()
    {
        return $this->curlOptions;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getCurlOption($key) {
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
    public function setMethod($method)
    {
        $this->method = strtoupper($method);
    }

    /**
     * @param array $post_fields
     */
    public function setPostFields(array $post_fields)
    {
        $this->postFields = $post_fields;
    }

    /**
     * @param array $post_files
     */
    public function setPostFiles(array $post_files)
    {
        $this->postFiles = $post_files;
    }

    /**
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * Sets the authorization credentials as header.
     *
     * @param string $username Username.
     * @param string $password Password.
     */
    public function setAuthorization($username, $password)
    {
        $this->setHeader('Authorization', 'Basic ' . base64_encode($username . ':' . $password));
    }

    /**
     * @param array $curlOptions
     */
    public function setCurlOptions(array $curlOptions)
    {
        $this->curlOptions = $curlOptions;
    }

    /**
     * @param $key
     * @param $value
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    /**
     * @param $key
     */
    public function removeHeader($key)
    {
        unset($this->headers[$key]);
    }

    /**
     * @param $key
     * @param $value
     */
    public function setPostField($key, $value)
    {
        $this->postFields[$key] = $value;
    }

    /**
     * @param $key
     * @param $value
     */
    public function setCurlOption($key, $value)
    {
        $this->curlOptions[$key] = $value;
    }

    public function addPostFile(array $file)
    {
        $this->postFiles[] = $file;
    }

}
