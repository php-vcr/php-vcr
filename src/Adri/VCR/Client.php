<?php
namespace Adri\PHPVCR;

class Client
{

    function __construct()
    {
        $this->client = new \Guzzle\Http\Client();
    }

    public function getClient()
    {
        return $this->client;
    }
}