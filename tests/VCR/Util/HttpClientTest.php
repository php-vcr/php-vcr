<?php

namespace VCR\Util;

use VCR\Request;

class HttpClientTest extends \PHPUnit_Framework_TestCase
{
    public function testHttpClientOnError()
    {
        $httpClient = new HttpClient();
        // Request on a closed port
        $request = new Request('GET', 'http://localhost:9934');

        $this->expectException('VCR\\VCRException');
        $httpClient->send($request);
    }
}
