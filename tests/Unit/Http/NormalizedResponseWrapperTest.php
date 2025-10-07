<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use VCR\Http\NormalizedResponseWrapper;

class NormalizedResponseWrapperTest extends TestCase
{
    public function testGetStatusCodePassesThrough(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $wrapper = new NormalizedResponseWrapper($mockResponse, fn ($e) => $e);

        $this->assertSame(200, $wrapper->getStatusCode());
    }

    public function testGetStatusCodeNormalizesException(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getStatusCode')
            ->willThrowException(new TransportException('Failed for "http://example.com/"'));

        $normalizer = function (TransportException $e) {
            return new TransportException('Failed for "http://example.com"'); // Removed trailing slash
        };

        $wrapper = new NormalizedResponseWrapper($mockResponse, $normalizer);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed for "http://example.com"');
        $wrapper->getStatusCode();
    }

    public function testGetHeadersPassesThrough(): void
    {
        $headers = ['content-type' => ['application/json']];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getHeaders')
            ->with(true)
            ->willReturn($headers);

        $wrapper = new NormalizedResponseWrapper($mockResponse, fn ($e) => $e);

        $this->assertSame($headers, $wrapper->getHeaders(true));
    }

    public function testGetHeadersNormalizesException(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getHeaders')
            ->willThrowException(new TransportException('Connection failed for "http://localhost:9959/"'));

        $normalizer = function (TransportException $e) {
            $message = preg_replace('#"(https?://[^"]+)/"#', '"$1"', $e->getMessage());
            if (null === $message) {
                $message = $e->getMessage();
            }

            return new TransportException($message);
        };

        $wrapper = new NormalizedResponseWrapper($mockResponse, $normalizer);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection failed for "http://localhost:9959"');
        $wrapper->getHeaders();
    }

    public function testGetContentPassesThrough(): void
    {
        $content = '{"data": "test"}';

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->with(true)
            ->willReturn($content);

        $wrapper = new NormalizedResponseWrapper($mockResponse, fn ($e) => $e);

        $this->assertSame($content, $wrapper->getContent(true));
    }

    public function testGetContentNormalizesException(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getContent')
            ->willThrowException(new TransportException('Timeout for "http://api.example.com"'));

        $normalizer = function (TransportException $e) {
            $message = rtrim($e->getMessage(), '/');
            return new TransportException($message);
        };

        $wrapper = new NormalizedResponseWrapper($mockResponse, $normalizer);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Timeout for "http://api.example.com"');
        $wrapper->getContent();
    }

    public function testToArrayPassesThrough(): void
    {
        $data = ['id' => 1, 'name' => 'test'];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('toArray')
            ->with(false)
            ->willReturn($data);

        $wrapper = new NormalizedResponseWrapper($mockResponse, fn ($e) => $e);

        $this->assertSame($data, $wrapper->toArray(false));
    }

    public function testToArrayNormalizesException(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('toArray')
            ->willThrowException(new TransportException('Parse error for "http://test.com/"'));

        $normalizer = function (TransportException $e) {
            $message = str_replace('/"', '"', $e->getMessage());
            return new TransportException($message);
        };

        $wrapper = new NormalizedResponseWrapper($mockResponse, $normalizer);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Parse error for "http://test.com"');
        $wrapper->toArray();
    }

    public function testCancelPassesThrough(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('cancel');

        $wrapper = new NormalizedResponseWrapper($mockResponse, fn ($e) => $e);

        $wrapper->cancel();
    }

    public function testGetInfoPassesThrough(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getInfo')
            ->with('total_time')
            ->willReturn(0.5);

        $wrapper = new NormalizedResponseWrapper($mockResponse, fn ($e) => $e);

        $this->assertSame(0.5, $wrapper->getInfo('total_time'));
    }

    public function testGetInfoWithNullTypeReturnsAllInfo(): void
    {
        $info = ['http_code' => 200, 'total_time' => 0.5];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->once())
            ->method('getInfo')
            ->with(null)
            ->willReturn($info);

        $wrapper = new NormalizedResponseWrapper($mockResponse, fn ($e) => $e);

        $this->assertSame($info, $wrapper->getInfo(null));
    }
}
