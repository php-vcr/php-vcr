<?php

namespace VCR;

/**
 * Test VCRs response object.
 */
class ResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testGetHeaders()
    {
        $expectedHeaders = array(
            'User-Agent' => 'Unit-Test',
            'Host'       => 'example.com'
        );

        $response = Response::fromArray(array('headers' => $expectedHeaders));

        $this->assertEquals($expectedHeaders, $response->getHeaders());
    }

    public function testGetHeadersNoneDefined()
    {
        $response = Response::fromArray(array());
        $this->assertEquals(array(), $response->getHeaders());
    }

    public function testRestoreHeadersFromArray()
    {
        $headers = array(
            'Content-Type'   => 'application/json',
            'Content-Length' => '349',
            'Connection'     => 'close',
            'Date'           => 'Fri, 31 Jan 2014 15:37:13 GMT',
        );
        $response = new Response(200, $headers);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($headers, $restoredResponse->getHeaders());
    }

    public function testGetBody()
    {
        $expectedBody = 'This is test content';

        $response = Response::fromArray(array('body' => $expectedBody));

        $this->assertEquals($expectedBody, $response->getBody(true));
    }

    public function testGetBodyNoneDefined()
    {
        $response = Response::fromArray(array());
        $this->assertEquals(null, $response->getBody(true));
    }

    public function testRestoreBodyFromArray()
    {
        $body = 'this is an example body';
        $response = new Response(200, array(), $body);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($body, $restoredResponse->getBody(true));
    }

    public function testBase64EncodeCompressedBody()
    {
        $body = 'this is an example body';
        $response = new Response(200, array('Content-Type' => 'application/x-gzip'), $body);
        $responseArray = $response->toArray();

        $this->assertEquals(base64_encode($body), $responseArray['body']);
    }

    public function testBase64DecodeCompressedBody()
    {
        $body = 'this is an example body';
        $responseArray = array(
            'headers' => array('Content-Type' => 'application/x-gzip'),
            'body'    => base64_encode($body)
        );
        $response = Response::fromArray($responseArray);

        $this->assertEquals($body, $response->getBody(true));
    }

    public function testRestoreCompressedBody()
    {
        $body = 'this is an example body';
        $response = new Response(200, array('Content-Type' => 'application/x-gzip'), $body);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($body, $restoredResponse->getBody(true));
    }

    public function testGetStatus()
    {
        $expectedStatus = 200;

        $response = new Response($expectedStatus);

        $this->assertEquals($expectedStatus, $response->getStatusCode());
    }

    public function testRestoreStatusFromArray()
    {
        $expectedStatus = 200;

        $response = new Response($expectedStatus);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($expectedStatus, $restoredResponse->getStatusCode());
    }

    public function testGetCurlInfo()
    {
        $curlOptions = array('option' => 'value');
        $response = new Response(200, array(), null, $curlOptions);

        $this->assertEquals($curlOptions, $response->getCurlInfo());
    }

    public function testGetCurlInfoFromArray()
    {
        $this->markTestSkipped('Is there a reason to store the cURL info?');
        $curlOptions = array('option' => 'value');

        $response = new Response(200, array(), null, $curlOptions);
        $restoredResponse = Response::fromArray($response->toArray());

        $this->assertEquals($curlOptions, $restoredResponse->getCurlInfo());
    }

    public function testToArray()
    {
        $expectedArray = array(
            'status'    => array(
                'http_version' => '1.1',
                'code' => 200,
                'message' => 'OK',
            ),
            'headers'   => array(
                'host' => 'example.com'
            ),
            'body'      => 'Test response'
        );

        $response = Response::fromArray($expectedArray);

        $this->assertEquals($expectedArray, $response->toArray());
    }
}
