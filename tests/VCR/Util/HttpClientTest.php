<?php

namespace VCR\Util;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class HttpClientTest extends TestCase
{
    public function testHttpClientOnError()
    {
        $httpClient = new HttpClient();
        // Request on a closed port
        $request = new Request('GET', 'http://localhost:9934');

        $this->expectException(CurlException::class);
        $httpClient->send($request);
    }
}
