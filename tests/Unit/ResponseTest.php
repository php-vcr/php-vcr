<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VCR\Response;

final class ResponseTest extends TestCase
{
    public function testGetHeaders(): void
    {
        $expectedHeaders = [
            'User-Agent' => 'Unit-Test',
            'Host' => 'example.com',
        ];

        $response = Response::fromArray(['headers' => $expectedHeaders]);

        $this->assertEquals($expectedHeaders, $response->getHeaders());
    }

    public function testGetHeadersNoneDefined(): void
    {
        $response = Response::fromArray([]);
        $this->assertEquals([], $response->getHeaders());
    }

    public function testRestoreHeadersFromArray(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Length' => '349',
            'Connection' => 'close',
            'Date' => 'Fri, 31 Jan 2014 15:37:13 GMT',
        ];
        $response = new Response('200', $headers);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($headers, $restoredResponse->getHeaders());
    }

    public function testGetBody(): void
    {
        $expectedBody = 'This is test content';

        $response = Response::fromArray(['body' => $expectedBody]);

        $this->assertEquals($expectedBody, $response->getBody());
    }

    public function testGetBodyNoneDefined(): void
    {
        $response = Response::fromArray([]);
        $this->assertEmpty($response->getBody());
    }

    public function testRestoreBodyFromArray(): void
    {
        $body = 'this is an example body';
        $response = new Response('200', [], $body);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($body, $restoredResponse->getBody());
    }

    public function testBase64EncodeCompressedBody(): void
    {
        $body = 'this is an example body';
        $response = new Response('200', ['Content-Type' => 'application/x-gzip'], $body);
        $responseArray = $response->toArray();

        $this->assertEquals(base64_encode($body), $responseArray['body']);
    }

    public function testBase64DecodeCompressedBody(): void
    {
        $body = 'this is an example body';
        $responseArray = [
            'headers' => ['Content-Type' => 'application/x-gzip'],
            'body' => base64_encode($body),
        ];
        $response = Response::fromArray($responseArray);

        $this->assertEquals($body, $response->getBody());
    }

    public function testRestoreCompressedBody(): void
    {
        $body = 'this is an example body';
        $response = new Response('200', ['Content-Type' => 'application/x-gzip'], $body);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($body, $restoredResponse->getBody());
    }

    public function testHttpVersionIsPreservedFromStatusArray(): void
    {
        $response = Response::fromArray([
            'status' => ['http_version' => '2', 'code' => 200, 'message' => 'OK'],
        ]);

        $this->assertSame('2', $response->getHttpVersion());
    }

    public function testHttpVersionIsNullWhenNotInStatusArray(): void
    {
        // Cassettes recorded without an http_version key must leave the field null
        // so that the replay layer (HttpUtil::formatAsStatusString) can apply the
        // fallback — the recording itself must not silently invent a version.
        $response = Response::fromArray(['status' => ['code' => 200, 'message' => 'OK']]);

        $this->assertNull($response->getHttpVersion());
    }

    public function testGetStatus(): void
    {
        $expectedStatus = '200';

        $response = new Response($expectedStatus);

        $this->assertEquals($expectedStatus, $response->getStatusCode());
    }

    public function testRestoreStatusFromArray(): void
    {
        $expectedStatus = '200';

        $response = new Response($expectedStatus);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($expectedStatus, $restoredResponse->getStatusCode());
    }

    public function testGetCurlInfo(): void
    {
        $curlOptions = ['option' => 'value'];
        $response = new Response('200', [], null, $curlOptions);

        $this->assertEquals($curlOptions, $response->getCurlInfo());
    }

    public function testRestoreCurlInfoFromArray(): void
    {
        $expectedCurlOptions = ['option' => 'value'];
        $response = new Response('200', [], null, $expectedCurlOptions);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($expectedCurlOptions, $response->getCurlInfo());
    }

    public function testGetHeaderReturnsFirstValueForDuplicateHeader(): void
    {
        $response = Response::fromArray([
            'headers' => [
                'Set-Cookie' => ['a=1; Path=/', 'b=2; Path=/'],
            ],
        ]);

        $this->assertSame('a=1; Path=/', $response->getHeader('Set-Cookie'));
    }

    public function testGetHeadersPreservesAllValuesForDuplicateHeader(): void
    {
        $values = ['a=1; Path=/', 'b=2; Path=/'];

        $response = Response::fromArray([
            'headers' => ['Set-Cookie' => $values],
        ]);

        $this->assertSame($values, $response->getHeaders()['Set-Cookie']);
    }

    public function testGetContentTypeReturnsFirstValueForDuplicateContentType(): void
    {
        $response = Response::fromArray([
            'headers' => [
                'Content-Type' => ['application/json; charset=utf-8', 'application/json; charset=utf-8'],
            ],
        ]);

        $this->assertSame('application/json; charset=utf-8', $response->getContentType());
    }

    public function testFromArrayDetectsGzipWithDuplicateContentType(): void
    {
        $body = 'this is an example body';
        $responseArray = [
            'headers' => ['Content-Type' => ['application/x-gzip', 'application/x-gzip']],
            'body' => base64_encode($body),
        ];

        $response = Response::fromArray($responseArray);

        $this->assertEquals($body, $response->getBody());
    }

    public function testFromArrayDetectsBinaryWithDuplicateContentTransferEncoding(): void
    {
        $body = 'this is an example body';
        $responseArray = [
            'headers' => ['Content-Transfer-Encoding' => ['binary', 'binary']],
            'body' => base64_encode($body),
        ];

        $response = Response::fromArray($responseArray);

        $this->assertEquals($body, $response->getBody());
    }

    public function testToArrayHandlesDuplicateContentTransferEncoding(): void
    {
        $body = 'this is an example body';
        $response = new Response('200', [
            'Content-Type' => 'application/octet-stream',
            'Content-Transfer-Encoding' => ['binary', 'binary'],
        ], $body);

        $responseArray = $response->toArray();

        $this->assertEquals(base64_encode($body), $responseArray['body']);
    }

    public function testToArray(): void
    {
        $expectedArray = [
            'status' => [
                'code' => 200,
                'message' => 'OK',
            ],
            'headers' => [
                'host' => 'example.com',
            ],
            'body' => 'Test response',
            'curl_info' => [
                'content_type' => 'text/html',
            ],
        ];

        $response = Response::fromArray($expectedArray);

        $this->assertEquals($expectedArray, $response->toArray());
    }
}
