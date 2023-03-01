<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\RequestMatcher;

final class RequestMatcherTest extends TestCase
{
    public function testMatchingMethod(): void
    {
        $first = new Request('GET', 'http://example.com', []);
        $second = new Request('GET', 'http://example.com', []);

        $this->assertTrue(RequestMatcher::matchMethod($first, $second));

        $first = new Request('GET', 'http://example.com', []);
        $second = new Request('POST', 'http://example.com', []);

        $this->assertFalse(RequestMatcher::matchMethod($first, $second));
    }

    public function testMatchingUrl(): void
    {
        $first = new Request('GET', 'http://example.com/common/path', []);
        $second = new Request('GET', 'http://example.com/common/path', []);

        $this->assertTrue(RequestMatcher::matchUrl($first, $second));

        $first = new Request('GET', 'http://example.com/first/path', []);
        $second = new Request('GET', 'http://example.com/second/path', []);

        $this->assertFalse(RequestMatcher::matchUrl($first, $second));

        $first = new Request('GET', 'http://example.com/second', []);
        $second = new Request('GET', 'http://example.com/second/path', []);

        $this->assertFalse(RequestMatcher::matchUrl($first, $second));
    }

    public function testMatchingHost(): void
    {
        $first = new Request('GET', 'http://example.com/common/path', []);
        $second = new Request('GET', 'http://example.com/common/path', []);

        $this->assertTrue(RequestMatcher::matchHost($first, $second));

        $first = new Request('GET', 'http://example.com/first/path', []);
        $second = new Request('GET', 'http://elpmaxe.com/second/path', []);

        $this->assertFalse(RequestMatcher::matchHost($first, $second));
    }

    public function testMatchingHeaders(): void
    {
        $first = new Request('GET', 'http://example.com', ['Accept' => 'Everything']);
        $second = new Request('GET', 'http://example.com', ['Accept' => 'Everything']);

        $this->assertTrue(RequestMatcher::matchHeaders($first, $second));

        $first = new Request('GET', 'http://example.com', ['Accept' => 'Everything']);
        $second = new Request('GET', 'http://example.com', ['Accept' => 'Nothing']);

        $this->assertFalse(RequestMatcher::matchHeaders($first, $second));
    }

    public function testHeaderMatchingDisallowsMissingHeaders(): void
    {
        $first = new Request('GET', 'http://example.com', ['Accept' => 'Everything', 'MyHeader' => 'value']);
        $second = new Request('GET', 'http://example.com', ['Accept' => 'Everything']);

        $this->assertFalse(RequestMatcher::matchHeaders($first, $second));

        $first = new Request('GET', 'http://example.com', ['Accept' => 'Everything']);
        $second = new Request('GET', 'http://example.com', ['Accept' => 'Everything', 'MyHeader' => 'value']);

        $this->assertFalse(RequestMatcher::matchHeaders($first, $second));
    }

    public function testHeaderMatchingAllowsEmptyVals(): void
    {
        $first = new Request('GET', 'http://example.com', ['Accept' => null, 'Content-Type' => 'application/json']);
        $second = new Request('GET', 'http://example.com', ['Accept' => null, 'Content-Type' => 'application/json']);

        $this->assertTrue(RequestMatcher::matchHeaders($first, $second));
    }

    public function testMatchingPostFields(): void
    {
        $mock = [
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => [],
            'post_fields' => [
                'field1' => 'value1',
                'field2' => 'value2',
            ],
        ];

        $first = Request::fromArray($mock);
        $second = Request::fromArray($mock);

        $this->assertTrue(RequestMatcher::matchPostFields($first, $second));

        $mock['post_fields']['field2'] = 'changedvalue2';
        $third = Request::fromArray($mock);

        $this->assertFalse(RequestMatcher::matchPostFields($first, $third));
    }

    public function testMatchingQueryString(): void
    {
        $first = new Request('GET', 'http://example.com/search?query=test', []);
        $second = new Request('GET', 'http://example.com/search?query=test', []);

        $this->assertTrue(RequestMatcher::matchQueryString($first, $second));

        $first = new Request('GET', 'http://example.com/search?query=first', []);
        $second = new Request('GET', 'http://example.com/search?query=second', []);

        $this->assertFalse(RequestMatcher::matchQueryString($first, $second));
    }

    public function testMatchingBody(): void
    {
        $first = new Request('GET', 'http://example.com', []);
        $first->setBody('test');
        $second = new Request('GET', 'http://example.com', []);
        $second->setBody('test');

        $this->assertTrue(RequestMatcher::matchBody($first, $second), 'Bodies should be equal');

        $first = new Request('GET', 'http://example.com', []);
        $first->setBody('test');
        $second = new Request('POST', 'http://example.com', []);
        $second->setBody('different');

        $this->assertFalse(RequestMatcher::matchBody($first, $second), 'Bodies are different.');
    }

    public function testMatchingSoapOperation(): void
    {
        $storedRequest = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => [],
            'body' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://tempuri.org\"><SOAP-ENV:Body><ns1:SearchAdresse><myPtr><cp>45000</cp></myPtr></ns1:SearchAdresse></SOAP-ENV:Body></SOAP-ENV:Envelope>\n",
        ]);

        $request = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => [],
            'body' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://tempuri.org\"><SOAP-ENV:Body><ns1:SearchAdresse><myPtr><cp>75008</cp></myPtr></ns1:SearchAdresse></SOAP-ENV:Body></SOAP-ENV:Envelope>\n",
        ]);
        $this->assertTrue(RequestMatcher::matchSoapOperation($storedRequest, $request), 'Operations are the same');

        $request = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => [],
            'body' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://tempuri.org\"><SOAP-ENV:Body><ns1:SearchFoo><myPtr><cp>75008</cp></myPtr></ns1:SearchFoo></SOAP-ENV:Body></SOAP-ENV:Envelope>\n",
        ]);
        $this->assertFalse(RequestMatcher::matchSoapOperation($storedRequest, $request), 'Operations are different');

        $request = Request::fromArray([
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => [],
            'body' => '{}',
        ]);
        $this->assertTrue(RequestMatcher::matchSoapOperation($storedRequest, $request), 'Operation is not SOAP message');
    }
}
