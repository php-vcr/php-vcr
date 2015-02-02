<?php

namespace VCR\Util;

use org\bovigo\vfs\vfsStream;
use VCR\Request;
use VCR\Response;

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

    public function testSetCurlOptionOnRequestPostFieldsQueryString()
    {
        $request = new Request('POST', 'example.com');
        $payload = 'para1=val1&para2=val2';

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestPostFieldsArray()
    {
        $request = new Request('POST', 'example.com');
        $payload = array('some' => 'test');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertNull($request->getBody());
        $this->assertEquals($payload, $request->getPostFields());
    }

    public function testSetCurlOptionOnRequestPostFieldsString()
    {
        $request = new Request('POST', 'example.com');
        $payload = json_encode(array('some' => 'test'));

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestSetSingleHeader()
    {
        $request = new Request('GET', 'example.com');
        $headers = array('Host: example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(array('Host' => 'example.com'), $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetSingleHeaderTwice()
    {
        $request = new Request('GET', 'example.com');
        $headers = array('Host: example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(array('Host' => 'example.com'), $request->getHeaders());
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
            'Host' => 'example.com',
            'Content-Type' => 'application/json'
        );
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestEmptyPostFieldsRemovesContentType()
    {
        $request = new Request('GET', 'example.com');
        $headers = array(
            'Host: example.com',
            'Content-Type: application/json',
        );

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, array());

        $expected = array(
            'Host' => 'example.com',
        );
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionReadFunctionToNull()
    {
	    $request = new Request('POST', 'example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, null, curl_init());

        $this->assertNull($request->getCurlOption(CURLOPT_READFUNCTION));
    }

    public function testSetCurlOptionReadFunctionMissingSize()
    {
        $this->setExpectedException('\VCR\VCRException', 'To set a CURLOPT_READFUNCTION, CURLOPT_INFILESIZE must be set.');
        $request = new Request('POST', 'example.com');

        $callback = function ($curlHandle, $fileHandle, $size) {};

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, $callback, curl_init());
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

    public function testHandleResponseReturnsBody()
    {
        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => true
        );
        $response = new Response(200, array(), 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($response->getBody(true), $output);
    }

    public function testHandleResponseEchosBody()
    {
        $response = new Response(200, array(), 'example response');

        ob_start();
        CurlHelper::handleOutput($response, array(), curl_init());
        $output = ob_get_clean();

        $this->assertEquals($response->getBody(true), $output);
    }

    public function testHandleResponseIncludesHeader()
    {
        $curlOptions = array(
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
        );
        $status = array(
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1'
        );
        $response = new Response($status, array(), 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n" . $response->getBody(), $output);
    }

    public function testHandleResponseUsesWriteFunction()
    {
        $test = $this;
        $expectedCh = curl_init();
        $expectedBody = 'example response';
        $curlOptions = array(
            CURLOPT_WRITEFUNCTION => function($ch, $body) use ($test, $expectedCh, $expectedBody) {
                $test->assertEquals($expectedCh, $ch);
                $test->assertEquals($expectedBody, $body);

                return strlen($body);
            }
        );
        $response = new Response(200, array(), $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, $expectedCh);
    }

    public function testHandleResponseWritesFile()
    {
        vfsStream::setup('test');
        $expectedBody = 'example response';
        $testFile = vfsStream::url('test/write_file');

        $curlOptions = array(
            CURLOPT_FILE => fopen($testFile, 'w+')
        );

        $response = new Response(200, array(), $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($expectedBody, file_get_contents($testFile));
    }
}
