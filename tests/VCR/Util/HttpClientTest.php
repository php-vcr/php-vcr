<?php

namespace VCR\Util;

use Guzzle\Http\Exception\BadResponseException;
use VCR\Response;
use VCR\Request;

class HttpClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Returns guzzle Client mock.
     *
     * @param  array  $methods Methodnames to overwrite.
     *
     * @return \Guzzle\Http\Client Guzzle Client mock.
     */
    protected function getGuzzleClientMock(array $methods = array())
    {
        return $this->getMockBuilder('\Guzzle\Http\Client')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    public function testCreateHttpClient()
    {
        $this->assertInstanceOf('\VCR\Util\HttpClient', new HttpClient());
    }

    public function testCreateHttpClientWithMock()
    {
        $this->assertInstanceOf('\VCR\Util\HttpClient', new HttpClient($this->getGuzzleClientMock()));
    }

    public function testSendHandlesBadResponseException()
    {
        $request = new Request('GET', 'https://example.com');
        $expected = new Response(200, null, 'sometest');

        $clientMock = $this->getGuzzleClientMock(array('send'));
        $clientMock
            ->expects($this->once())
            ->method('send')
            ->with($request)
            ->will($this->throwException(BadResponseException::factory($request, $expected)));

        $httpClient = new HttpClient($clientMock);
        $actual = $httpClient->send($request);

        $this->assertEquals($expected, $actual, 'Expected response from BadResponseException.');
    }

    public function testSendRecievesResponseFromClient()
    {
        $request = new Request('GET', 'https://example.com');
        $expected = new Response(200, null, 'sometest');

        $clientMock = $this->getGuzzleClientMock(array('send'));
        $clientMock
            ->expects($this->once())
            ->method('send')
            ->with($request)
            ->will($this->returnValue($expected));

        $httpClient = new HttpClient($clientMock);
        $actual = $httpClient->send($request);

        $this->assertEquals($expected, $actual, 'Expected response from client send method.');
    }
}
