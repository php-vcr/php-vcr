<?php

namespace VCR\Util;

use VCR\Request;

class CurlHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testSetCurlOptionOnRequestSetSingleHeader()
    {
        $request = new Request('GET', 'example.com');
        $headers = array('Host: example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(array('host' => 'example.com'), $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetSingleHeaderTwice()
    {
        $request = new Request('GET', 'example.com');
        $headers = array('Host: example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(array('host' => 'example.com'), $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetMultipleHeadersTwice()
    {
        $request = new Request('GET', 'example.com');
        $headers = array(
            'Host: example.com',
            'Content-Type: application/json',
        );

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $expected = array(
            'host' => 'example.com',
            'content-type' => 'application/json'
        );
        $this->assertEquals($expected, $request->getHeaders());
    }
}
