<?php

namespace VCR;

use const CURLOPT_CUSTOMREQUEST;
use PHPUnit\Framework\TestCase;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class RequestTest extends TestCase
{
    /**
     * @var \VCR\Request
     */
    protected $request;

    public function setUp()
    {
        $this->request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
    }

    public function testGetHeaders()
    {
        $this->assertEquals(
            [
                'User-Agent' => 'Unit-Test',
                'Host' => 'example.com',
            ],
            $this->request->getHeaders()
        );
    }

    public function testSetMethod()
    {
        $this->request->setMethod('post');

        $this->assertEquals('POST', $this->request->getMethod());
    }

    public function testSetAuthorization()
    {
        $this->request->setAuthorization('login', 'password');

        $this->assertEquals('Basic bG9naW46cGFzc3dvcmQ=', $this->request->getHeader('Authorization'));
    }

    public function testMatches()
    {
        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);

        $this->assertTrue($this->request->matches($request, [['VCR\RequestMatcher', 'matchMethod']]));
    }

    public function testDoesntMatch()
    {
        $request = new Request('POST', 'http://example.com', ['User-Agent' => 'Unit-Test']);

        $this->assertFalse($this->request->matches($request, [['VCR\RequestMatcher', 'matchMethod']]));
    }

    public function testMatchesThrowsExceptionIfMatcherNotFound()
    {
        $request = new Request('POST', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $this->expectException(
            '\BadFunctionCallException',
            "Matcher could not be executed. Array\n(\n    [0] => some\n    [1] => method\n)\n"
        );
        $this->request->matches($request, [['some', 'method']]);
    }

    public function testRestoreRequest()
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

    public function testStorePostFields()
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

    public function testRestorePostFields()
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

    public function testStorePostFile()
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

    public function testSetPostFiles()
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

    public function testRestorePostFiles()
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

    public function testRestoreBody()
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

    public function testMatchesBody()
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

    public function testDoesntMatchBody()
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

    public function testGetHostReturnsBothHostAndPort()
    {
        $request = new Request('GET', 'http://example.com:5000/foo?param=key');
        $this->assertEquals('example.com:5000', $request->getHost());
    }

    public function testDoNotOverwriteHostHeader()
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

    public function testCurlCustomRequestOverridesMethod()
    {
        $postRequest = new Request('POST', 'http://example.com');
        $getRequest = new Request('GET', 'http://example.com');

        $this->assertEquals('POST', $postRequest->getMethod());
        $this->assertEquals('GET', $getRequest->getMethod());

        $postRequest->setCurlOption(CURLOPT_CUSTOMREQUEST, 'PUT');
        $getRequest->setCurlOption(CURLOPT_CUSTOMREQUEST, 'POST');

        $this->assertEquals('PUT', $postRequest->getMethod());
        $this->assertEquals('POST', $getRequest->getMethod());
    }

    public function testSetCurlOptions()
    {
        $getRequest = new Request('GET', 'http://example.com');
        $getRequest->setCurlOptions([
            CURLOPT_CUSTOMREQUEST => 'PUT',
        ]);
        $this->assertEquals('PUT', $getRequest->getCurlOption(CURLOPT_CUSTOMREQUEST));
    }
}
