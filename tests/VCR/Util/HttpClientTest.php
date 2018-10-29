<?php

namespace VCR\Util;

use PHPUnit\Framework\TestCase;
use VCR\Response;
use VCR\Request;

class HttpClientTest extends TestCase
{
    public function testHttpClientOnError()
    {
        $httpClient = new HttpClient();
        // Request on a closed port
        $request = new Request('GET', 'http://localhost:9934');

        $this->setExpectedException('VCR\\Util\\CurlException');
        $httpClient->send($request);
    }
}
