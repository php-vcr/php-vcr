<?php

namespace VCR;

class Response extends \Guzzle\Http\Message\Response
{
    public function toArray()
    {
        $body = (string) $this->getBody(true);

        // Base64 encode when binary
        if (strpos($this->getContentType(), 'application/x-gzip') !== false) {
           $body = base64_encode($body);
        }

        return array_filter(array(
            'status'    => $this->getStatusCode(),
            'headers'   => $this->getHeaders(),
            'body'      => $body,
            'curl_info' => $this->getInfo()
        ));
    }

    public function getHeaders($asObjects = false)
    {
        if ($asObjects === true) {
            return parent::getHeaders($asObjects);
        }

        $headers = array();
        foreach (parent::getHeaders() as $key => $value) {
            $values = $header->toArray();
            $headers[$header->getName()] = $values[0];
        }

        return $headers;
    }

    public static function fromArray(array $response)
    {
        $body = isset($response['body']) ? $response['body'] : null;

        // Base64 decode when binary
        if (isset($response['headers']['Content-Type'])
           && strpos($response['headers']['Content-Type'], 'application/x-gzip') !== false) {
            $body = base64_decode($response['body']);
        }

        $responseObject = new static(
            isset($response['status']) ? $response['status'] : 200,
            isset($response['headers']) ? $response['headers'] : array(),
            $body
        );

        if (isset($response['curl_info'])) {
            $responseObject->setInfo($response['curl_info']);
        }

        return $responseObject;
    }
}
