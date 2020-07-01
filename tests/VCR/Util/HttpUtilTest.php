<?php

namespace VCR\Util;

use PHPUnit\Framework\TestCase;

class HttpUtilTest extends TestCase
{
    public function testParseResponseBasic()
    {
        $raw = "HTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        list($status, $headers, $body) = HttpUtil::parseResponse($raw);

        $expectedHeaders = [
            'Content-Type: text/html',
            'Date: Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary: Accept-Encoding',
            'Content-Length: 0',
        ];

        $this->assertEquals('HTTP/1.1 201 Created', $status);
        $this->assertEquals('', $body);
        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testParseResponseMultipleHeaders()
    {
        $raw = "HTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept, Accept-Language, Expect\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        list($status, $headers, $body) = HttpUtil::parseResponse($raw);

        $expectedHeaders = [
            'Content-Type: text/html',
            'Date: Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary: Accept, Accept-Language, Expect',
            'Vary: Accept-Encoding',
            'Content-Length: 0',
        ];

        $this->assertEquals('HTTP/1.1 201 Created', $status);
        $this->assertEquals('', $body);
        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testParseContinuePlusResponse()
    {
        $raw = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        list($status, $headers, $body) = HttpUtil::parseResponse($raw);

        $expectedHeaders = [
            'Content-Type: text/html',
            'Date: Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary: Accept-Encoding',
            'Content-Length: 0',
        ];

        $this->assertEquals('HTTP/1.1 201 Created', $status);
        $this->assertEquals('', $body);
        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testParseiMultipleContinuePlusResponse()
    {
        $raw = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        list($status, $headers, $body) = HttpUtil::parseResponse($raw);

        $expectedHeaders = [
            'Content-Type: text/html',
            'Date: Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary: Accept-Encoding',
            'Content-Length: 0',
        ];

        $this->assertEquals('HTTP/1.1 201 Created', $status);
        $this->assertEquals('', $body);
        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testParseContinuePlusResponseMultipleHeaders()
    {
        $raw = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept, Accept-Language, Expect\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        list($status, $headers, $body) = HttpUtil::parseResponse($raw);

        $expectedHeaders = [
            'Content-Type: text/html',
            'Date: Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary: Accept, Accept-Language, Expect',
            'Vary: Accept-Encoding',
            'Content-Length: 0',
        ];

        $this->assertEquals('HTTP/1.1 201 Created', $status);
        $this->assertEquals('', $body);
        $this->assertEquals($expectedHeaders, $headers);
    }

    public function testParseHeadersBasic()
    {
        $inputArray = [
            'Content-Type: text/html',
            'Date: Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary: Accept-Encoding',
            'Content-Length: 0',
        ];
        $excpetedHeaders = [
            'Content-Type' => 'text/html',
            'Date' => 'Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary' => 'Accept-Encoding',
            'Content-Length' => '0',
        ];
        $outputArray = HttpUtil::parseHeaders($inputArray);
        $this->assertEquals($excpetedHeaders, $outputArray);
    }

    public function testParseHeadersMultiple()
    {
        $inputArray = [
            'Content-Type: text/html',
            'Date: Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary: Accept, Accept-Language, Expect',
            'Vary: Accept-Encoding',
            'Content-Length: 0',
        ];
        $excpetedHeaders = [
            'Content-Type' => 'text/html',
            'Date' => 'Fri, 19 Jun 2015 16:05:18 GMT',
            'Vary' => ['Accept, Accept-Language, Expect', 'Accept-Encoding'],
            'Content-Length' => '0',
        ];
        $outputArray = HttpUtil::parseHeaders($inputArray);
        $this->assertEquals($excpetedHeaders, $outputArray);
    }

    public function testParseHeadersIncludingColons()
    {
        $inputArray = [
            'dropbox-api-result: {"name": "a_file.txt"}',
        ];
        $excpetedHeaders = [
            'dropbox-api-result' => '{"name": "a_file.txt"}',
        ];
        $outputArray = HttpUtil::parseHeaders($inputArray);
        $this->assertEquals($excpetedHeaders, $outputArray);
    }
}
