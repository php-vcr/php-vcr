<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VCR\Request;

final class RequestTest extends TestCase
{
    protected Request $request;

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
    }

    public function testGetHeaders(): void
    {
        $this->assertEquals(
            [
                'User-Agent' => 'Unit-Test',
                'Host' => 'example.com',
            ],
            $this->request->getHeaders()
        );
    }

    public function testSetMethod(): void
    {
        $this->request->setMethod('post');

        $this->assertEquals('POST', $this->request->getMethod());
    }

    public function testSetAuthorization(): void
    {
        $this->request->setAuthorization('login', 'password');

        $this->assertEquals('Basic bG9naW46cGFzc3dvcmQ=', $this->request->getHeader('Authorization'));
    }

    public function testMatches(): void
    {
        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);

        $this->assertTrue($this->request->matches($request, [['VCR\RequestMatcher', 'matchMethod']]));
    }

    public function testDoesntMatch(): void
    {
        $request = new Request('POST', 'http://example.com', ['User-Agent' => 'Unit-Test']);

        $this->assertFalse($this->request->matches($request, [['VCR\RequestMatcher', 'matchMethod']]));
    }

    public function testMatchesThrowsExceptionIfMatcherNotFound(): void
    {
        $request = new Request('POST', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $this->expectException(\BadFunctionCallException::class);
        $this->expectExceptionMessage("Matcher could not be executed. Array\n(\n    [0] => some\n    [1] => method\n)\n");
        $this->request->matches($request, [['some', 'method']]);
    }

    public function testRestoreRequest(): void
    {
        $restoredRequest = Request::fromArray($this->request->toArray());
        $this->assertEquals(
            [
                'method' => 'GET',
                'url' => 'http://example.com',
                'headers' => [
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                ],
            ],
            $restoredRequest->toArray()
        );
    }

    public function testStorePostFields(): void
    {
        $this->request->setPostFields(['para1' => 'val1']);
        $this->assertEquals(
            [
                'method' => 'GET',
                'url' => 'http://example.com',
                'headers' => [
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                ],
                'post_fields' => ['para1' => 'val1'],
            ],
            $this->request->toArray()
        );
    }

    public function testRestorePostFields(): void
    {
        $this->request->setPostFields(['para1' => 'val1']);
        $restoredRequest = Request::fromArray($this->request->toArray());
        $this->assertEquals(
            [
                'method' => 'GET',
                'url' => 'http://example.com',
                'headers' => [
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                ],
                'post_fields' => ['para1' => 'val1'],
            ],
            $restoredRequest->toArray()
        );
    }

    public function testStorePostFile(): void
    {
        $file = [
            'fieldName' => 'field_name',
            'contentType' => 'application/octet-stream',
            'filename' => 'tests/fixtures/unittest_curl_test',
            'postname' => 'unittest_curl_test',
        ];
        $this->request->addPostFile($file);
        $this->assertEquals(
            [
                'method' => 'GET',
                'url' => 'http://example.com',
                'headers' => [
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                ],
                'post_files' => [$file],
            ],
            $this->request->toArray()
        );
    }

    public function testSetPostFiles(): void
    {
        $file = [
            'fieldName' => 'field_name',
            'contentType' => 'application/octet-stream',
            'filename' => 'tests/fixtures/unittest_curl_test',
            'postname' => 'unittest_curl_test',
        ];
        $this->request->setPostFiles([$file]);
        $this->assertEquals(
            [
                'method' => 'GET',
                'url' => 'http://example.com',
                'headers' => [
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                ],
                'post_files' => [$file],
            ],
            $this->request->toArray()
        );
    }

    public function testRestorePostFiles(): void
    {
        $file = [
            'fieldName' => 'field_name',
            'contentType' => 'application/octet-stream',
            'filename' => 'tests/fixtures/unittest_curl_test',
            'postname' => 'unittest_curl_test',
        ];
        $this->request->addPostFile($file);
        $restoredRequest = Request::fromArray($this->request->toArray());
        $this->assertEquals(
            [
                'method' => 'GET',
                'url' => 'http://example.com',
                'headers' => [
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                ],
                'post_files' => [$file],
            ],
            $restoredRequest->toArray()
        );
    }

    public function testRestoreBody(): void
    {
        $this->request->setBody('sometest');
        $restoredRequest = Request::fromArray($this->request->toArray());
        $this->assertEquals(
            [
                'method' => 'GET',
                'url' => 'http://example.com',
                'headers' => [
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                ],
                'body' => 'sometest',
            ],
            $restoredRequest->toArray()
        );
    }

    public function testMatchesBody(): void
    {
        $this->request->setBody('sometest');
        $request = new Request('POST', 'http://example.com');
        $request->setBody('sometest');

        $this->assertTrue(
            $this->request->matches(
                Request::fromArray($request->toArray()),
                [['VCR\RequestMatcher', 'matchBody']]
            )
        );
    }

    public function testDoesntMatchBody(): void
    {
        $this->request->setBody('sometest');
        $request = new Request('POST', 'http://example.com');
        $request->setBody('not match');

        $this->assertFalse(
            $this->request->matches(
                Request::fromArray($request->toArray()),
                [['VCR\RequestMatcher', 'matchBody']]
            )
        );
    }

    public function testGetHostReturnsBothHostAndPort(): void
    {
        $request = new Request('GET', 'http://example.com:5000/foo?param=key');
        $this->assertEquals('example.com:5000', $request->getHost());
    }

    public function testDoNotOverwriteHostHeader(): void
    {
        $this->request = new Request(
            'GET',
            'http://example.com',
            ['User-Agent' => 'Unit-Test', 'Host' => 'www.example.com']
        );

        $this->assertEquals(
            [
                'User-Agent' => 'Unit-Test',
                'Host' => 'www.example.com',
            ],
            $this->request->getHeaders()
        );
    }

    public function testCurlCustomRequestOverridesMethod(): void
    {
        $postRequest = new Request('POST', 'http://example.com');
        $getRequest = new Request('GET', 'http://example.com');

        $this->assertEquals('POST', $postRequest->getMethod());
        $this->assertEquals('GET', $getRequest->getMethod());

        $postRequest->setCurlOption(\CURLOPT_CUSTOMREQUEST, 'PUT');
        $getRequest->setCurlOption(\CURLOPT_CUSTOMREQUEST, 'POST');

        $this->assertEquals('PUT', $postRequest->getMethod());
        $this->assertEquals('POST', $getRequest->getMethod());
    }

    public function testSetCurlOptions(): void
    {
        $getRequest = new Request('GET', 'http://example.com');
        $getRequest->setCurlOptions([
            \CURLOPT_CUSTOMREQUEST => 'PUT',
        ]);
        $this->assertEquals('PUT', $getRequest->getCurlOption(\CURLOPT_CUSTOMREQUEST));
    }
}
