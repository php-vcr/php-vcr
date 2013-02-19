<?php

namespace Adri\VCR;

class Response extends \Guzzle\Http\Message\Response
{
    public function toArray()
    {
        return array(
            'status'  => $this->getStatusCode(),
            'headers' => $this->getHeaders()->getAll(),
            'body'    => $this->getBody(true)
        );
    }

    public static function fromArray(array $response)
    {
        return new static(
            $response['status'],
            $response['headers'],
            $response['body']
        );
    }
}
