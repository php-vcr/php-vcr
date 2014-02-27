<?php

namespace VCR\Util;

use VCR\Request;

class CurlHelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getHttpMethodsProvider()
     */
    public function testSetCurlOptionMethods($method)
    {
        $request = new Request($method, 'example.com');
        $headers = array('Host: example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals($method, $request->getMethod());
    }

    /**
     * Returns a list of HTTP methods for testing testSetCurlOptionMethods.
     *
     * @return array HTTP methods.
     */
    public function getHttpMethodsProvider()
    {
        return array(
            array('CONNECT'),
            array('DELETE'),
            array('GET'),
            array('HEAD'),
            array('OPTIONS'),
            array('POST'),
            array('PUT'),
            array('TRACE'),
        );
    }

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

    public function testSetCurlOptionReadFunctionMissingSize()
    {
        $this->setExpectedException('\VCR\VCRException', 'To set a CURLOPT_READFUNCTION, CURLOPT_INFILESIZE must be set.');
        $request = new Request('POST', 'example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, null, curl_init());

        $this->assertEquals($expected, $request->getBody());
    }

    public function testSetCurlOptionReadFunction()
    {
        $expected = 'test body';
        $request = new Request('POST', 'example.com');

        $test = $this;
        $callback = function ($curlHandle, $fileHandle, $size) use ($test, $expected) {
            $test->assertInternalType('resource', $curlHandle);
            $test->assertInternalType('resource', $fileHandle);
            $test->assertEquals(strlen($expected), $size);

            return $expected;
        };

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_INFILESIZE, strlen($expected));
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, $callback, curl_init());

        $this->assertEquals($expected, $request->getBody());
    }
}
