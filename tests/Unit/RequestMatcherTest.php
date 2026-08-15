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

    /**
     * @dataProvider matchingJsonBodiesProvider
     */
    public function testMatchingBodyJsonMatches(?string $storedBody, ?string $body, string $message): void
    {
        $this->assertTrue(
            RequestMatcher::matchBodyJson(
                $this->createRequestWithBody($storedBody),
                $this->createRequestWithBody($body)
            ),
            $message
        );
    }

    /**
     * @dataProvider nonMatchingJsonBodiesProvider
     */
    public function testMatchingBodyJsonDoesNotMatch(?string $storedBody, ?string $body, string $message): void
    {
        $this->assertFalse(
            RequestMatcher::matchBodyJson(
                $this->createRequestWithBody($storedBody),
                $this->createRequestWithBody($body)
            ),
            $message
        );
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string}>
     */
    public static function matchingJsonBodiesProvider(): array
    {
        return [
            'identical bodies' => ['{"a":1,"b":2}', '{"a":1,"b":2}', 'Identical JSON bodies match'],
            'reordered object keys' => ['{"a":1,"b":2}', '{"b":2,"a":1}', 'Object key order is ignored'],
            'reformatted body' => ['{"a":1,"b":2}', "{\n  \"a\" : 1,\n  \"b\" : 2\n}", 'Formatting is ignored'],
            'reordered nested object keys' => [
                '{"o":{"x":1,"y":{"p":true,"q":null}}}',
                '{"o":{"y":{"q":null,"p":true},"x":1}}',
                'Object key order is ignored at any depth',
            ],
            'identical arrays' => ['["a","b"]', '["a","b"]', 'Arrays in the same order match'],
            'empty objects' => ['{}', '{}', 'Empty objects match'],
            'both bodies unset' => [null, null, 'Two absent bodies match'],
            'both bodies empty' => ['', '', 'Two empty bodies match'],
            'identical invalid json' => ['{"a":1', '{"a":1', 'Invalid JSON falls back to a string comparison'],
            'identical whitespace only' => ['   ', '   ', 'A whitespace only body falls back to a string comparison'],
            'identical non json bodies' => ['plain text', 'plain text', 'Non JSON bodies fall back to a string comparison'],
            'identical bodies with leading whitespace' => [
                "  \n{\"a\":1}",
                "  \n{\"a\":1}",
                'Identical bodies match without being decoded',
            ],
            'leading whitespace is ignored' => ["\n\t {\"a\":1,\"b\":2}", '{"b":2,"a":1}', 'Leading whitespace is ignored'],
        ];
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string}>
     */
    public static function nonMatchingJsonBodiesProvider(): array
    {
        return [
            'reordered array elements' => ['["a","b"]', '["b","a"]', 'Array element order is significant'],
            'reordered nested array elements' => [
                '{"m":[{"r":"a"},{"r":"b"}]}',
                '{"m":[{"r":"b"},{"r":"a"}]}',
                'Array element order is significant at any depth',
            ],
            'integer against string' => ['{"a":1}', '{"a":"1"}', 'Scalar types are compared strictly'],
            'integer against float' => ['{"a":1}', '{"a":1.0}', 'Integers and floats are different'],
            'integer against boolean' => ['{"a":1}', '{"a":true}', 'Integers and booleans are different'],
            'null against empty string' => ['{"a":null}', '{"a":""}', 'Null and an empty string are different'],
            'object against array' => ['{}', '[]', 'An object is not an array'],
            'numeric keys object against array' => ['{"0":"a"}', '["a"]', 'An object with numeric keys is not an array'],
            'extra key' => ['{"a":1}', '{"a":1,"b":2}', 'An extra key is a mismatch'],
            'missing key' => ['{"a":1,"b":2}', '{"a":1}', 'A missing key is a mismatch'],
            'longer array' => ['["a"]', '["a","b"]', 'A different array length is a mismatch'],
            'different invalid json' => ['not json', 'also not json', 'Invalid JSON falls back to a string comparison'],
            'only one side is json' => ['{"a":1}', 'plain text', 'A JSON body does not match a non JSON body'],
            'absent against empty object' => [null, '{}', 'An absent body does not match an empty object'],
            'big integers beyond php int max' => [
                '{"id":9223372036854775808}',
                '{"id":9223372036854775809}',
                'Integers beyond PHP_INT_MAX are compared exactly',
            ],
            'scalar bodies' => ['5', '5.0', 'Bare scalar bodies fall back to a string comparison'],
            'xml bodies' => ['<a><b/></a>', '<a><c/></a>', 'XML bodies fall back to a string comparison'],
            'binary bodies' => ["\x00\x01\x02", "\x00\x01\x03", 'Binary bodies fall back to a string comparison'],
        ];
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

    private function createRequestWithBody(?string $body): Request
    {
        $request = new Request('POST', 'http://example.com', []);

        if (null !== $body) {
            $request->setBody($body);
        }

        return $request;
    }
}
