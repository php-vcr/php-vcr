<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Exceptions\InvalidHostException;
use VCR\Request;
use VCR\Response;
use VCR\Util\CurlHelper;

final class CurlHelperTest extends TestCase
{
    /** @var string[] */
    private $headersFound;

    /**
     * @dataProvider getHttpMethodsProvider()
     */
    public function testSetCurlOptionMethods(string $method): void
    {
        $request = new Request($method, 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals($method, $request->getMethod());
    }

    /**
     * @return array<string[]>
     */
    public static function getHttpMethodsProvider(): array
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

    public function testSetCurlOptionOnRequestPostFieldsQueryString(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = 'para1=val1&para2=val2';

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestPostFieldsArray(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = ['some' => 'test'];

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_POSTFIELDS, $payload);

        $this->assertNull($request->getBody());
        $this->assertEquals($payload, $request->getPostFields());
    }

    public function testSetCurlOptionOnRequestPostFieldsString(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = json_encode(['some' => 'test']);

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals($payload, (string) $request->getBody());
    }

    public function testSetCurlOptionOnRequestPostFieldsEmptyString(): void
    {
        $request = new Request('POST', 'http://example.com');
        $payload = '';

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_POSTFIELDS, $payload);

        // This is consistent with how requests are read out of storage using
        // \VCR\Request::fromArray(array $request).
        $this->assertNull($request->getBody());
    }

    public function testSetCurlOptionOnRequestSetSingleHeader(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(['Host' => 'example.com'], $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetSingleHeaderTwice(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = ['Host: example.com'];

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_HTTPHEADER, $headers);

        $this->assertEquals(['Host' => 'example.com'], $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestSetMultipleHeadersTwice(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = [
            'Host: example.com',
            'Content-Type: application/json',
        ];

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_HTTPHEADER, $headers);

        $expected = [
            'Host' => 'example.com',
            'Content-Type' => 'application/json',
        ];
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestEmptyPostFieldsRemovesContentType(): void
    {
        $request = new Request('GET', 'http://example.com');
        $headers = [
            'Host: example.com',
            'Content-Type: application/json',
        ];

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_HTTPHEADER, $headers);
        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_POSTFIELDS, []);

        $expected = [
            'Host' => 'example.com',
        ];
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testSetCurlOptionOnRequestPostFieldsSetsPostMethod(): void
    {
        $request = new Request('GET', 'http://example.com');
        $payload = json_encode(['some' => 'test']);

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_POSTFIELDS, $payload);

        $this->assertEquals('POST', $request->getMethod());
    }

    public function testSetCurlOptionReadFunctionToNull(): void
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_READFUNCTION, null);

        $this->assertNull($request->getCurlOption(\CURLOPT_READFUNCTION));
    }

    public function testInvalidHostException(): void
    {
        $this->expectException(InvalidHostException::class);
        $this->expectExceptionMessage('Could not read host from URL "example.com". Please check the URL syntax.');
        new Request('POST', 'example.com');
    }

    public function testSetCurlOptionReadFunctionMissingSize(): void
    {
        $this->expectException(\VCR\VCRException::class);
        $this->expectExceptionMessage('To set a CURLOPT_READFUNCTION, CURLOPT_INFILESIZE must be set.');
        $request = new Request('POST', 'http://example.com');

        $callback = static function ($curlHandle, $fileHandle, $size): void {
        };

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_READFUNCTION, $callback);
        CurlHelper::validateCurlPOSTBody($request, curl_init());
    }

    public function testSetCurlOptionReadFunction(): void
    {
        $expected = 'test body';
        $request = new Request('POST', 'http://example.com');

        $test = $this;
        $callback = static function ($curlHandle, $fileHandle, $size) use ($test, $expected) {
            $test->assertNotFalse($curlHandle);
            $test->assertIsResource($fileHandle);
            $test->assertEquals(\strlen($expected), $size);

            return $expected;
        };

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_INFILESIZE, \strlen($expected));
        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_READFUNCTION, $callback);
        CurlHelper::validateCurlPOSTBody($request, curl_init());

        $this->assertEquals($expected, $request->getBody());
    }

    public function testHandleResponseReturnsBody(): void
    {
        $curlOptions = [
            \CURLOPT_RETURNTRANSFER => true,
        ];
        $response = new Response('200', [], 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($response->getBody(), $output);
    }

    public function testHandleResponseEchosBody(): void
    {
        $response = new Response('200', [], 'example response');

        ob_start();
        CurlHelper::handleOutput($response, [], curl_init());
        $output = ob_get_clean();

        $this->assertEquals($response->getBody(), $output);
    }

    public function testHandleResponseIncludesHeader(): void
    {
        $curlOptions = [
            \CURLOPT_HEADER => true,
            \CURLOPT_RETURNTRANSFER => true,
        ];
        $status = [
            'code' => '200',
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $response = new Response($status, [], 'example response');

        $output = CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n".$response->getBody(), $output);
    }

    public function testHandleOutputHeaderFunction(): void
    {
        $actualHeaders = [];
        $curlOptions = [
            \CURLOPT_HEADERFUNCTION => static function ($ch, $header) use (&$actualHeaders): void {
                $actualHeaders[] = $header;
            },
        ];
        $status = [
            'code' => '200',
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => '0',
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            "HTTP/1.1 200 OK\r\n",
            "Content-Length: 0\r\n",
            "\r\n",
        ];
        $this->assertEquals($expected, $actualHeaders);
    }

    public function testHandleOutputHeaderFunctionWithPublicFunction(): void
    {
        $this->headersFound = [];
        $curlOptions = [
            \CURLOPT_HEADERFUNCTION => [$this, 'publicCurlHeaderFunction'],
        ];
        $status = [
            'code' => '200',
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => '0',
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            "HTTP/1.1 200 OK\r\n",
            "Content-Length: 0\r\n",
            "\r\n",
        ];
        $this->assertEquals($expected, $this->headersFound);
    }

    public function testHandleOutputHeaderFunctionWithProtectedFunction(): void
    {
        $this->headersFound = [];
        $curlOptions = [
            \CURLOPT_HEADERFUNCTION => [$this, 'protectedCurlHeaderFunction'],
        ];
        $status = [
            'code' => '200',
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => '0',
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            "HTTP/1.1 200 OK\r\n",
            "Content-Length: 0\r\n",
            "\r\n",
        ];
        $this->assertEquals($expected, $this->headersFound);
    }

    public function testHandleOutputHeaderFunctionWithPrivateFunction(): void
    {
        $this->headersFound = [];
        $curlOptions = [
            \CURLOPT_HEADERFUNCTION => [$this, 'privateCurlHeaderFunction'],
        ];
        $status = [
            'code' => '200',
            'message' => 'OK',
            'http_version' => '1.1',
        ];
        $headers = [
            'Content-Length' => '0',
        ];
        $response = new Response($status, $headers, 'example response');
        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $expected = [
            "HTTP/1.1 200 OK\r\n",
            "Content-Length: 0\r\n",
            "\r\n",
        ];
        $this->assertEquals($expected, $this->headersFound);
    }

    public function testHandleResponseUsesWriteFunction(): void
    {
        $test = $this;
        $expectedCh = curl_init();
        $expectedBody = 'example response';
        $curlOptions = [
            \CURLOPT_WRITEFUNCTION => static function ($ch, $body) use ($test, $expectedCh, $expectedBody) {
                $test->assertEquals($expectedCh, $ch);
                $test->assertEquals($expectedBody, $body);

                return \strlen($body);
            },
        ];
        $response = new Response('200', [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, $expectedCh);
    }

    public function testHandleResponseUsesWriteFunctionWithPrivateFunction(): void
    {
        $test = $this;
        $expectedCh = curl_init();
        $expectedBody = 'example response';
        $curlOptions = [
            \CURLOPT_WRITEFUNCTION => [$this, 'privateCurlWriteFunction'],
        ];
        $response = new Response('200', [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, $expectedCh);
    }

    public function testHandleResponseWritesFile(): void
    {
        vfsStream::setup('test');
        $expectedBody = 'example response';
        $testFile = vfsStream::url('test/write_file');

        $curlOptions = [
            \CURLOPT_FILE => fopen($testFile, 'w+'),
        ];

        $response = new Response('200', [], $expectedBody);

        CurlHelper::handleOutput($response, $curlOptions, curl_init());

        $this->assertEquals($expectedBody, file_get_contents($testFile));
    }

    /**
     * @dataProvider getCurlOptionProvider()
     *
     * @param int   $curlOption              cURL option to get
     * @param mixed $expectedCurlOptionValue Expected value of cURL option
     */
    public function testGetCurlOptionFromResponse(Response $response, $curlOption, $expectedCurlOptionValue): void
    {
        $this->assertEquals(
            $expectedCurlOptionValue,
            CurlHelper::getCurlOptionFromResponse($response, $curlOption)
        );
    }

    /** @return array<mixed> */
    public static function getCurlOptionProvider(): array
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
                \CURLINFO_HEADER_SIZE,
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
                \CURLINFO_HEADER_SIZE,
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
                \CURLINFO_HEADER_SIZE,
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
                \CURLINFO_HEADER_SIZE,
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
                \CURLINFO_HEADER_SIZE,
                290,
            ],
            'certinfo defaults to empty array when not recorded' => [
                Response::fromArray(['status' => 200, 'headers' => []]),
                \CURLINFO_CERTINFO,
                [],
            ],
            'certinfo returns recorded data from cassette' => [
                Response::fromArray([
                    'status' => 200,
                    'headers' => [],
                    'curl_info' => ['certinfo' => [['Subject' => 'CN=example.com']]],
                ]),
                \CURLINFO_CERTINFO,
                [['Subject' => 'CN=example.com']],
            ],
        ];
    }

    public function testSetCurlOptionCustomRequest(): void
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_CUSTOMREQUEST, 'PUT');

        $this->assertEquals('PUT', $request->getCurlOption(\CURLOPT_CUSTOMREQUEST));
    }

    public function testCurlCustomRequestAlwaysOverridesMethod(): void
    {
        $request = new Request('POST', 'http://example.com');

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_CUSTOMREQUEST, 'DELETE');

        $this->assertEquals('DELETE', $request->getMethod());

        CurlHelper::setCurlOptionOnRequest($request, \CURLOPT_POSTFIELDS, ['some' => 'test']);

        $this->assertEquals('DELETE', $request->getMethod());
    }

    /**
     * Function used for testing CURLOPT_HEADERFUNCTION.
     *
     * @param resource $ch
     */
    public function publicCurlHeaderFunction($ch, string $header): void
    {
        $this->headersFound[] = $header;
    }

    /**
     * Function used for testing CURLOPT_HEADERFUNCTION.
     *
     * @param resource $ch
     */
    protected function protectedCurlHeaderFunction($ch, string $header): void
    {
        $this->headersFound[] = $header;
    }

    /**
     * Function used for testing CURLOPT_HEADERFUNCTION.
     *
     * @param resource $ch
     */
    private function privateCurlHeaderFunction($ch, string $header): void
    {
        $this->headersFound[] = $header;
    }

    /**
     * @dataProvider getDefaultCurlInfoProvider()
     */
    public function testGetDefaultCurlInfo(int $option, mixed $expected, ?string $url = null): void
    {
        $this->assertSame($expected, CurlHelper::getDefaultCurlInfo($option, $url));
    }

    /** @return array<string, mixed> */
    public static function getDefaultCurlInfoProvider(): array
    {
        return [
            'http_code returns 0' => [\CURLINFO_HTTP_CODE, 0],
            'redirect_count returns 0' => [\CURLINFO_REDIRECT_COUNT, 0],
            'header_size returns 0' => [\CURLINFO_HEADER_SIZE, 0],
            'request_size returns 0' => [\CURLINFO_REQUEST_SIZE, 0],
            'ssl_verify_result returns 0' => [\CURLINFO_SSL_VERIFYRESULT, 0],
            'filetime returns -1' => [\CURLINFO_FILETIME, -1],
            'download_content_length returns -1.0' => [\CURLINFO_CONTENT_LENGTH_DOWNLOAD, -1.0],
            'upload_content_length returns -1.0' => [\CURLINFO_CONTENT_LENGTH_UPLOAD, -1.0],
            'size_upload returns 0.0' => [\CURLINFO_SIZE_UPLOAD, 0.0],
            'size_download returns 0.0' => [\CURLINFO_SIZE_DOWNLOAD, 0.0],
            'speed_download returns 0.0' => [\CURLINFO_SPEED_DOWNLOAD, 0.0],
            'speed_upload returns 0.0' => [\CURLINFO_SPEED_UPLOAD, 0.0],
            'total_time returns 0.0' => [\CURLINFO_TOTAL_TIME, 0.0],
            'namelookup_time returns 0.0' => [\CURLINFO_NAMELOOKUP_TIME, 0.0],
            'connect_time returns 0.0' => [\CURLINFO_CONNECT_TIME, 0.0],
            'pretransfer_time returns 0.0' => [\CURLINFO_PRETRANSFER_TIME, 0.0],
            'starttransfer_time returns 0.0' => [\CURLINFO_STARTTRANSFER_TIME, 0.0],
            'redirect_time returns 0.0' => [\CURLINFO_REDIRECT_TIME, 0.0],
            'appconnect_time returns 0.0' => [\CURLINFO_APPCONNECT_TIME, 0.0],
            'certinfo returns []' => [\CURLINFO_CERTINFO, []],
            'content_type returns null' => [\CURLINFO_CONTENT_TYPE, null],
            'effective_url without url returns empty string' => [\CURLINFO_EFFECTIVE_URL, ''],
            'effective_url uses provided url' => [\CURLINFO_EFFECTIVE_URL, 'http://example.com', 'http://example.com'],
            'unknown option returns false' => [999999, false],
        ];
    }

    public function testGetDefaultCurlInfoAllReturns23Keys(): void
    {
        $info = CurlHelper::getDefaultCurlInfo(0, 'http://example.com');

        $this->assertIsArray($info);
        $this->assertCount(23, $info);
        $this->assertSame('http://example.com', $info['url']);
        $this->assertSame(0, $info['http_code']);
        $this->assertSame(-1, $info['filetime']);
        $this->assertSame([], $info['certinfo']);
        $this->assertNull($info['content_type']);
        $this->assertSame(-1.0, $info['download_content_length']);
        $this->assertSame(-1.0, $info['upload_content_length']);
        $this->assertSame(0.0, $info['total_time']);
    }

    public function testGetDefaultCurlInfoAllUsesNullUrlWhenNoUrl(): void
    {
        $info = CurlHelper::getDefaultCurlInfo(0, null);

        $this->assertIsArray($info);
        $this->assertSame('', $info['url']);
    }

    public function testGetCurlOptionFromResponseHandleCertinfo(): void
    {
        $response = new Response('200', [], 'example response');

        $this->assertEquals(
            [],
            CurlHelper::getCurlOptionFromResponse($response, \CURLINFO_CERTINFO)
        );
    }

    /**
     * Function used for testing CURLOPT_WRITEFUNCTION.
     */
    private function privateCurlWriteFunction(\CurlHandle $ch, string $body): int
    {
        $this->assertEquals('example response', $body);

        return \strlen($body);
    }
}
