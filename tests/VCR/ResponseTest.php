<?php

namespace VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class ResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testGetHeaders()
    {
        $expectedHeaders = array(
            'user-agent' => 'Unit-Test',
            'host'       => 'example.com'
        );

        $response = Response::fromArray(array('headers' => $expectedHeaders));

        $this->assertEquals($expectedHeaders, $response->getHeaders());
    }

    public function testGetHeadersNoneDefined()
    {
        $response = Response::fromArray(array());
        $this->assertEquals(array(), $response->getHeaders());
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

    public function testGetStatus()
    {
        $expectedStatus = 200;

        $response = Response::fromArray(array('status' => $expectedStatus));

        $this->assertEquals($expectedStatus, $response->getStatusCode());
    }

    public function testToArray()
    {
        $expectedArray = array(
            'status'    => 200,
            'headers'   => array(
                'host' => 'example.com'
            ),
            'body'      => 'Test response'
        );

        $response = Response::fromArray($expectedArray);

        $this->assertEquals($expectedArray, $response->toArray());
    }

}
