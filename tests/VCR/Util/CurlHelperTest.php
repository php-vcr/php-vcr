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

    public function testSetCurlOptionOnRequestPostFieldsEmptyString()
    {
        $request = new Request('POST', 'example.com');
        $payload = '';

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        // This is consistent with how requests are read out of storage using
        // \VCR\Request::fromArray(array $request).
        $this->assertNull($request->getBody());
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

    public function testSetCurlOptionOnRequestPostFieldsSetsPostMethod()
    {
        $request = new Request('GET', 'example.com');
        $payload = json_encode(array('some' => 'test'));

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals('POST', $request->getMethod());
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

        $callback = function ($curlHandle, $fileHandle, $size) {
        };

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, $callback, curl_init());
        CurlHelper::validateCurlPOSTBody($request, curl_init());
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
        CurlHelper::validateCurlPOSTBody($request, curl_init());

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

    public function testHandleOutputHeaderFunction()
    {
        $actualHeaders = array();
        $curlOptions = array(
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$actualHeaders) {
                $actualHeaders[] = $header;
            },
        );
        $status = array(
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        );
        $headers = array(
            'Content-Length' => 0,
        );
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = array(
            'HTTP/1.1 200 OK',
            'Content-Length: 0',
            ''
        );
        $this->assertEquals($expected, $actualHeaders);
    }

    public function testHandleResponseUsesWriteFunction()
    {
        $test = $this;
        $expectedCh = curl_init();
        $expectedBody = 'example response';
        $curlOptions = array(
            CURLOPT_WRITEFUNCTION => function ($ch, $body) use ($test, $expectedCh, $expectedBody) {
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

    public function testGetCurlOptionFromResponseHandleCertinfo()
    {
        $status = array(
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        );
        $headers = array(
            'Content-Length' => 0,
        );
        $response = new Response($status, $headers, 'example response');

        $this->assertEquals(
            array(),
            CurlHelper::getCurlOptionFromResponse($response, CURLINFO_CERTINFO)
        );
    }

    /**
     * @dataProvider getCurlOptionProvider()
     *
     * @param Response $response
     * @param integer $curlOption cURL option to get.
     * @param mixed $expectedCurlOptionValue Expected value of cURL option
     */
    public function testGetCurlOptionFromResponse(Response $response, $curlOption, $expectedCurlOptionValue)
    {
        $this->assertEquals(
            $expectedCurlOptionValue,
            CurlHelper::getCurlOptionFromResponse($response, $curlOption)
        );
    }

    public function getCurlOptionProvider()
    {
        return array(
            array(
                Response::fromArray(
                    array(
                        'status'    => array(
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ),
                        'headers'   => array(
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',

                        ),
                    )
                ),
                CURLINFO_HEADER_SIZE,
                100,
            ),
            array(
                Response::fromArray(
                    array(
                        'status'    => array(
                            'http_version' => '1.1',
                            'code' => 404,
                            'message' => 'Not Found',
                        ),
                        'headers'   => array(
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',

                        ),
                    )
                ),
                CURLINFO_HEADER_SIZE,
                107,
            ),
            array(
                Response::fromArray(
                    array(
                        'status'    => array(
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ),
                        'headers'   => array(
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                            'X-Powered-By' => 'PHP/5.6.4-4ubuntu6',
                        ),
                    )
                ),
                CURLINFO_HEADER_SIZE,
                134,
            ),
            array(
                Response::fromArray(
                    array(
                        'status'    => array(
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ),
                        'headers'   => array(
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                            'Cache-Control' => 'no-cache, must-revalidate',
                            'Pragma' => 'no-cache',
                        ),
                    )
                ),
                CURLINFO_HEADER_SIZE,
                160,
            ),
            array(
                Response::fromArray(
                    array(
                        'status'    => array(
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ),
                        'headers'   => array(
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'X-Powered-By' => 'PHP/5.6.4-4ubuntu6',
                            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
                            'Last-Modified' => 'Sat, 13 Jun 2015 20:36:15 GMT',
                            'Cache-Control' => 'no-store, no-cache, must-revalidate',
                            'Pragma' => 'no-cache',
                            'Content-type' => 'text/html; charset=UTF-8',
                        ),
                    )
                ),
                CURLINFO_HEADER_SIZE,
                290,
            ),
        );
    }

    public function testSetCurlOptionCustomRequest()
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_CUSTOMREQUEST, 'PUT');

        $this->assertEquals('PUT', $request->getCurlOption(CURLOPT_CUSTOMREQUEST));
    }

    public function testCurlCustomRequestAlwaysOverridesMethod()
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_CUSTOMREQUEST, 'DELETE');

        $this->assertEquals('DELETE', $request->getMethod());

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, array('some' => 'test'));

        $this->assertEquals('DELETE', $request->getMethod());
    }
}
