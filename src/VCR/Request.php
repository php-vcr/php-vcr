<?php

namespace VCR;

use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\PostFile;

/**
 * Encapsulates a HTTP request.
 */
class Request extends EntityEnclosingRequest
{
    /**
     * Returns true if specified request maches the current one
     * with specified request matcher callbacks.
     *
     * @param  Request      $request         Request to check if it matches the current one.
     * @param  \callable[]  $requestMatchers Request matcher callbacks.
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
     * Returns a response for current request.
     *
     * @return Response Response for current request.
     */
    public function send()
    {
        $response = parent::send();

        return new Response($response->getStatusCode(), $response->getHeaders(), $response->getBody());
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
     * Returns an array representation of this request.
     *
     * @return array Array representation of this request.
     */
    public function toArray()
    {
        return array_filter(
            array(
                'method'      => $this->getMethod(),
                'url'         => $this->getUrl(),
                'headers'     => $this->getHeaders(),
                'body'        => (string) $this->getBody(),
                'post_files'  => $this->getPostFilesArray(),
                'post_fields' => (array) $this->getPostFields()->toArray(),
            )
        );
    }

    /**
     * Returns a list of post files.
     *
     * @return array List of post files.
     */
    public function getPostFilesArray()
    {
        $postFileList = array();

        foreach ($this->getPostFiles() as $files) {
            foreach ($files as $file) {
                $postFileList[] = array(
                    'fieldName'   => $file->getFieldName(),
                    'contentType' => $file->getContentType(),
                    'filename'    => $file->getFilename(),
                    'postname'    => $file->getPostname(),
                );
            }
        }

        return $postFileList;
    }

    /**
     * Returns a list of headers as key/value pairs.
     *
     * @param  boolean $asObjects (Optional) returns the headers as object.
     * @return array List of headers as key/value pairs.
     */
    public function getHeaders($asObjects = false)
    {
        if ($asObjects === true) {
            return parent::getHeaders();
        }

        $headers = array();

        /* @var \Guzzle\Http\Message\Header $header */
        foreach (parent::getHeaders()->getAll() as $header) {
            $headers[$header->getName()] = (string) $header;
        }

        return $headers;
    }

    /**
     * Creates a new Request from a specified array.
     *
     * @param  array  $request Request represented as an array.
     *
     * @return Request A new Request from specified array.
     */
    public static function fromArray(array $request)
    {
        $requestObject = new Request(
            $request['method'],
            $request['url'],
            $request['headers']
        );

        if (!empty($request['post_fields']) && is_array($request['post_fields'])) {
            $requestObject->addPostFields($request['post_fields']);
        }

        if (!empty($request['post_files']) && is_array($request['post_files'])) {
            foreach ($request['post_files'] as $file) {
                $requestObject->addPostFile(
                    new PostFile(
                        $file['fieldName'],
                        $file['filename'],
                        $file['contentType'],
                        $file['postname']
                    )
                );
            }
        }

        if (!empty($request['body'])) {
            $requestObject->setBody((string) $request['body']);
        }

        return $requestObject;
    }
}
