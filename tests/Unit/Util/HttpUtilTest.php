<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use VCR\Util\HttpUtil;

final class HttpUtilTest extends TestCase
{
    public function testParseResponseBasic(): void
    {
        $raw = "HTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        [$status, $headers, $body] = HttpUtil::parseResponse($raw);

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

    public function testParseResponseMultipleHeaders(): void
    {
        $raw = "HTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept, Accept-Language, Expect\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        [$status, $headers, $body] = HttpUtil::parseResponse($raw);

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

    public function testParseContinuePlusResponse(): void
    {
        $raw = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        [$status, $headers, $body] = HttpUtil::parseResponse($raw);

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

    public function testParseiMultipleContinuePlusResponse(): void
    {
        $raw = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        [$status, $headers, $body] = HttpUtil::parseResponse($raw);

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

    public function testParseContinuePlusResponseMultipleHeaders(): void
    {
        $raw = "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 201 Created\r\nContent-Type: text/html\r\nDate: Fri, 19 Jun 2015 16:05:18 GMT\r\nVary: Accept, Accept-Language, Expect\r\nVary: Accept-Encoding\r\nContent-Length: 0\r\n\r\n";
        [$status, $headers, $body] = HttpUtil::parseResponse($raw);

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

    public function testParseHeadersBasic(): void
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

    public function testParseHeadersMultiple(): void
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

    public function testParseHeadersIncludingColons(): void
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
