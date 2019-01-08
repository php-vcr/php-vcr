<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class SoapOperationMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new SoapOperationMatcher();

        $storedRequest = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => array(),
            'body' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://tempuri.org\"><SOAP-ENV:Body><ns1:SearchAdresse><myPtr><cp>45000</cp></myPtr></ns1:SearchAdresse></SOAP-ENV:Body></SOAP-ENV:Envelope>\n"
        ]);

        $request = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => array(),
            'body' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://tempuri.org\"><SOAP-ENV:Body><ns1:SearchAdresse><myPtr><cp>75008</cp></myPtr></ns1:SearchAdresse></SOAP-ENV:Body></SOAP-ENV:Envelope>\n"
        ]);
        $this->assertTrue($matcher->match($storedRequest, $request), 'Operations are the same');

        $request = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => array(),
            'body' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://tempuri.org\"><SOAP-ENV:Body><ns1:SearchFoo><myPtr><cp>75008</cp></myPtr></ns1:SearchFoo></SOAP-ENV:Body></SOAP-ENV:Envelope>\n"
        ]);
        $this->assertFalse($matcher->match($storedRequest, $request), 'Operations are different');

        $request = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => array(),
            'body' => '{}'
        ]);
        $this->assertTrue($matcher->match($storedRequest, $request), 'Operation is not SOAP message');

        $this->assertFalse($matcher->match($request, $storedRequest), 'Stored opration is not SOAP message');
    }
}
