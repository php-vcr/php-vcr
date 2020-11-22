<?php

namespace VCR\Util;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Exceptions\InvalidHostException;
use VCR\Request;
use VCR\Response;

class CurlHelperTest extends TestCase
{
    /**
     * @dataProvider getHttpMethodsProvider()
     */
    public function testSetCurlOptionMethods($method)
    {
        $request = new Request($method, 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals($method, $request->getMethod());
    }

    /**
     * Returns a list of HTTP methods for testing testSetCurlOptionMethods.
     *
     * @return array HTTP methods
     */
    public function getHttpMethodsProvider()
    {
        return [
            ['CONNECT'],
            ['DELETE'],
            ['GET'],
            ['HEAD'],
            ['OPTIONS'],
            ['POST'],
            ['PUT'],
            ['TRACE'],
        ];
    }

    public function testSetCurlOptionOnRequestPostFieldsQueryString()
    {
        $request = new Request('POST', 'http://example.com');
        $payload = 'para1=val1&para2=val2';

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestPostFieldsArray()
    {
        $request = new Request('POST', 'http://example.com');
        $payload = ['some' => 'test'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertNull($request->getBody());
        $this->assertEquals($payload, $request->getPostFields());
    }

    public function testSetCurlOptionOnRequestPostFieldsString()
    {
        $request = new Request('POST', 'http://example.com');
        $payload = json_encode(['some' => 'test']);

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestPostFieldsEmptyString()
    {
        $request = new Request('POST', 'http://example.com');
        $payload = '';

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        // This is consistent with how requests are read out of storage using
        // \VCR\Request::fromArray(array $request).
        $this->assertNull($request->getBody());
    }

    public function testSetCurlOptionOnRequestSetSingleHeader()
    {
        $request = new Request('GET', 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(['Host' => 'example.com'], $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetSingleHeaderTwice()
    {
        $request = new Request('GET', 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(['Host' => 'example.com'], $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetMultipleHeadersTwice()
    {
        $request = new Request('GET', 'http://example.com');
        $headers = [
            'Host: example.com',
            'Content-Type: application/json',
        ];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);

        $expected = [
            'Host' => 'example.com',
            'Content-Type' => 'application/json',
        ];
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestEmptyPostFieldsRemovesContentType()
    {
        $request = new Request('GET', 'http://example.com');
        $headers = [
            'Host: example.com',
            'Content-Type: application/json',
        ];

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, []);

        $expected = [
            'Host' => 'example.com',
        ];
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestPostFieldsSetsPostMethod()
    {
        $request = new Request('GET', 'http://example.com');
        $payload = json_encode(['some' => 'test']);

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals('POST', $request->getMethod());
    }

    public function testSetCurlOptionReadFunctionToNull()
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, null, curl_init());

        $this->assertNull($request->getCurlOption(CURLOPT_READFUNCTION));
    }

    public function testInvalidHostException()
    {
        $this->expectException(InvalidHostException::class, 'URL must be valid.');
        new Request('POST', 'example.com');
    }

    public function testSetCurlOptionReadFunctionMissingSize()
    {
        $this->expectException('\VCR\VCRException', 'To set a CURLOPT_READFUNCTION, CURLOPT_INFILESIZE must be set.');
        $request = new Request('POST', 'http://example.com');

        $callback = function ($curlHandle, $fileHandle, $size) {
        };

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, $callback, curl_init());
        CurlHelper::validateCurlPOSTBody($request, curl_init());
    }

    public function testSetCurlOptionReadFunction()
    {
        $expected = 'test body';
        $request = new Request('POST', 'http://example.com');

        $test = $this;
        $callback = function ($curlHandle, $fileHandle, $size) use ($test, $expected) {
            $test->assertInternalType('resource', $curlHandle);
            $test->assertInternalType('resource', $fileHandle);
            $test->assertEquals(\strlen($expected), $size);

            return $expected;
        };

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_INFILESIZE, \strlen($expected));
        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_READFUNCTION, $callback, curl_init());
        CurlHelper::validateCurlPOSTBody($request, curl_init());

        $this->assertEquals($expected, $request->getBody());
    }

    public function testHandleResponseReturnsBody()
    {
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
        ];
        $response = new Response(200, [], 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($response->getBody(true), $output);
    }

    public function testHandleResponseEchosBody()
    {
        $response = new Response(200, [], 'example response');

        ob_start();
        CurlHelper::handleOutput($response, [], curl_init());
        $output = ob_get_clean();

        $this->assertEquals($response->getBody(true), $output);
    }

    public function testHandleResponseIncludesHeader()
    {
        $curlOptions = [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
        ];
        $status = [
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $response = new Response($status, [], 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n".$response->getBody(), $output);
    }

    public function testHandleOutputHeaderFunction()
    {
        $actualHeaders = [];
        $curlOptions = [
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$actualHeaders) {
                $actualHeaders[] = $header;
            },
        ];
        $status = [
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => 0,
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            'HTTP/1.1 200 OK',
            'Content-Length: 0',
            '',
        ];
        $this->assertEquals($expected, $actualHeaders);
    }

    public function testHandleOutputHeaderFunctionWithPublicFunction()
    {
        $this->headersFound = [];
        $curlOptions = [
            CURLOPT_HEADERFUNCTION => [$this, 'publicCurlHeaderFunction'],
        ];
        $status = [
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => 0,
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            'HTTP/1.1 200 OK',
            'Content-Length: 0',
            '',
        ];
        $this->assertEquals($expected, $this->headersFound);
    }

    public function testHandleOutputHeaderFunctionWithProtectedFunction()
    {
        $this->headersFound = [];
        $curlOptions = [
            CURLOPT_HEADERFUNCTION => [$this, 'protectedCurlHeaderFunction'],
        ];
        $status = [
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => 0,
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            'HTTP/1.1 200 OK',
            'Content-Length: 0',
            '',
        ];
        $this->assertEquals($expected, $this->headersFound);
    }

    public function testHandleOutputHeaderFunctionWithPrivateFunction()
    {
        $this->headersFound = [];
        $curlOptions = [
            CURLOPT_HEADERFUNCTION => [$this, 'privateCurlHeaderFunction'],
        ];
        $status = [
            'code' => 200,
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => 0,
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            'HTTP/1.1 200 OK',
            'Content-Length: 0',
            '',
        ];
        $this->assertEquals($expected, $this->headersFound);
    }

    public function testHandleResponseUsesWriteFunction()
    {
        $test = $this;
        $expectedCh = curl_init();
        $expectedBody = 'example response';
        $curlOptions = [
            CURLOPT_WRITEFUNCTION => function ($ch, $body) use ($test, $expectedCh, $expectedBody) {
                $test->assertEquals($expectedCh, $ch);
                $test->assertEquals($expectedBody, $body);

                return \strlen($body);
            },
        ];
        $response = new Response(200, [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, $expectedCh);
    }

    public function testHandleResponseUsesWriteFunctionWithPrivateFunction()
    {
        $test = $this;
        $expectedCh = curl_init();
        $expectedBody = 'example response';
        $curlOptions = [
            CURLOPT_WRITEFUNCTION => [$this, 'privateCurlWriteFunction'],
        ];
        $response = new Response(200, [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, $expectedCh);
    }

    public function testHandleResponseWritesFile()
    {
        vfsStream::setup('test');
        $expectedBody = 'example response';
        $testFile = vfsStream::url('test/write_file');

        $curlOptions = [
            CURLOPT_FILE => fopen($testFile, 'w+'),
        ];

        $response = new Response(200, [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($expectedBody, file_get_contents($testFile));
    }

    /**
     * @dataProvider getCurlOptionProvider()
     *
     * @param int   $curlOption              cURL option to get
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
        return [
            [
                Response::fromArray(
                    [
                        'status' => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers' => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                100,
            ],
            [
                Response::fromArray(
                    [
                        'status' => [
                            'http_version' => '1.1',
                            'code' => 404,
                            'message' => 'Not Found',
                        ],
                        'headers' => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                107,
            ],
            [
                Response::fromArray(
                    [
                        'status' => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers' => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                            'X-Powered-By' => 'PHP/5.6.4-4ubuntu6',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                134,
            ],
            [
                Response::fromArray(
                    [
                        'status' => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers' => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'Content-type' => 'text/html; charset=UTF-8',
                            'Cache-Control' => 'no-cache, must-revalidate',
                            'Pragma' => 'no-cache',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                160,
            ],
            [
                Response::fromArray(
                    [
                        'status' => [
                            'http_version' => '1.1',
                            'code' => 200,
                            'message' => 'OK',
                        ],
                        'headers' => [
                            'Host' => 'localhost:8000',
                            'Connection' => 'close',
                            'X-Powered-By' => 'PHP/5.6.4-4ubuntu6',
                            'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
                            'Last-Modified' => 'Sat, 13 Jun 2015 20:36:15 GMT',
                            'Cache-Control' => 'no-store, no-cache, must-revalidate',
                            'Pragma' => 'no-cache',
                            'Content-type' => 'text/html; charset=UTF-8',
                        ],
                    ]
                ),
                CURLINFO_HEADER_SIZE,
                290,
            ],
        ];
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

        CurlHelper::setCurlOptionOnRequest($request, CURLOPT_POSTFIELDS, ['some' => 'test']);

        $this->assertEquals('DELETE', $request->getMethod());
    }

    // Function used for testing CURLOPT_HEADERFUNCTION
    public function publicCurlHeaderFunction($ch, $header)
    {
        $this->headersFound[] = $header;
    }

    // Function used for testing CURLOPT_HEADERFUNCTION
    protected function protectedCurlHeaderFunction($ch, $header)
    {
        $this->headersFound[] = $header;
    }

    // Function used for testing CURLOPT_HEADERFUNCTION
    private function privateCurlHeaderFunction($ch, $header)
    {
        $this->headersFound[] = $header;
    }

    // Function used for testing CURLOPT_WRITEFUNCTION
    private function privateCurlWriteFunction($ch, $body)
    {
        $this->assertEquals('resource', \gettype($ch));
        $this->assertEquals('example response', $body);

        return \strlen($body);
    }
}
