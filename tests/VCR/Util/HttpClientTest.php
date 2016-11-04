<?php

namespace VCR\Util;

use VCR\Response;
use VCR\Request;

class HttpClientTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateHttpClient()
    {
        $this->assertInstanceOf('\VCR\Util\HttpClient', new HttpClient());
    }

    public function testCreateHttpClientWithMock()
    {
        $this->assertInstanceOf('\VCR\Util\HttpClient', new HttpClient());
    }

    public function testHttpClientRequestThrowsExceptionOnCurlError()
    {
        $request = new Request('GET', 'http://error.test', array('User-Agent' => 'Unit-Test'));
        $client = new HttpClient();

        $this->setExpectedException(
            'VCR\VCRException',
            "Couldn't resolve host 'error.test'"
        );

        $client->send($request);
    }
}
