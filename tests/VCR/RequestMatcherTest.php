<?php

namespace VCR;

class RequestMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testMatchingMethod()
    {
        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('GET', 'http://example.com', array());

        $this->assertTrue(RequestMatcher::matchMethod($first, $second));

        $first = new Request('GET', 'http://example.com', array());
        $second = new Request('POST', 'http://example.com', array());

        $this->assertFalse(RequestMatcher::matchMethod($first, $second));
    }

    public function testMatchingUrl()
    {
        $first = new Request('GET', 'http://example.com/common/path', array());
        $second = new Request('GET', 'http://example.com/common/path', array());

        $this->assertTrue(RequestMatcher::matchUrl($first, $second));

        $first = new Request('GET', 'http://example.com/first/path', array());
        $second = new Request('GET', 'http://example.com/second/path', array());

        $this->assertFalse(RequestMatcher::matchUrl($first, $second));

        $first = new Request('GET', 'http://example.com/second', array());
        $second = new Request('GET', 'http://example.com/second/path', array());

        $this->assertFalse(RequestMatcher::matchUrl($first, $second));
    }

    public function testMatchingHost()
    {
        $first = new Request('GET', 'http://example.com/common/path', array());
        $second = new Request('GET', 'http://example.com/common/path', array());

        $this->assertTrue(RequestMatcher::matchHost($first, $second));

        $first = new Request('GET', 'http://example.com/first/path', array());
        $second = new Request('GET', 'http://elpmaxe.com/second/path', array());

        $this->assertFalse(RequestMatcher::matchHost($first, $second));
    }

    public function testMatchingHeaders()
    {
        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));

        $this->assertTrue(RequestMatcher::matchHeaders($first, $second));

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Nothing'));

        $this->assertFalse(RequestMatcher::matchHeaders($first, $second));
    }

    public function testHeaderMatchingDisallowsMissingHeaders()
    {
        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything', 'MyHeader' => 'value'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));

        $this->assertFalse(RequestMatcher::matchHeaders($first, $second));

        $first = new Request('GET', 'http://example.com', array('Accept' => 'Everything'));
        $second = new Request('GET', 'http://example.com', array('Accept' => 'Everything', 'MyHeader' => 'value'));

        $this->assertFalse(RequestMatcher::matchHeaders($first, $second));
    }

    public function testHeaderMatchingAllowsEmptyVals()
    {
        $first = new Request('GET', 'http://example.com', array('Accept' => null, 'Content-Type' => 'application/json'));
        $second = new Request('GET', 'http://example.com', array('Accept' => null, 'Content-Type' => 'application/json'));

        $this->assertTrue(RequestMatcher::matchHeaders($first, $second));
    }

    public function testMatchingPostFields()
    {
        $mock = array(
            'method' => 'POST',
            'url' => 'http://example.com',
            'headers' => array(),
            'post_fields' => array(
                'field1' => 'value1',
                'field2' => 'value2',
            )
        );

        $first = Request::fromArray($mock);
        $second = Request::fromArray($mock);

        $this->assertTrue(RequestMatcher::matchPostFields($first, $second));

        $mock['post_fields']['field2'] = 'changedvalue2';
        $third = Request::fromArray($mock);

        $this->assertFalse(RequestMatcher::matchPostFields($first, $third));
    }

    public function testMatchingQueryString()
    {
        $first = new Request('GET', 'http://example.com/search?query=test', array());
        $second = new Request('GET', 'http://example.com/search?query=test', array());

        $this->assertTrue(RequestMatcher::matchQueryString($first, $second));

        $first = new Request('GET', 'http://example.com/search?query=first', array());
        $second = new Request('GET', 'http://example.com/search?query=second', array());

        $this->assertFalse(RequestMatcher::matchQueryString($first, $second));
    }

    public function testMatchingBody()
    {
        $first = new Request('GET', 'http://example.com', array());
        $first->setBody('test');
        $second = new Request('GET', 'http://example.com', array());
        $second->setBody('test');

        $this->assertTrue(RequestMatcher::matchBody($first, $second), 'Bodies should be equal');

        $first = new Request('GET', 'http://example.com', array());
        $first->setBody('test');
        $second = new Request('POST', 'http://example.com', array());
        $second->setBody('different');

        $this->assertFalse(RequestMatcher::matchBody($first, $second), 'Bodies are different.');
    }
}
