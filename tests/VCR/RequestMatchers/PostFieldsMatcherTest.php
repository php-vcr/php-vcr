<?php

namespace VCR\RequestMatchers;

use \VCR\Request;

class PostFieldsMatcherTest extends RequestMatcherTestCase
{
    private $matcher;

    public function setUp() {
        $this->matcher = new PostFieldsMatcher();
    }

    public function testMatch()
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

        $this->assertTrue($this->matcher->match($first, $second));

        $mock['post_fields']['field2'] = 'changedvalue2';
        $third = Request::fromArray($mock);

        $this->assertFalse($this->matcher->match($first, $third));
    }

    public function testGetMismatchMessage() {
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
        $mock['post_fields']['field2'] = 'changedvalue2';
        $second = Request::fromArray($mock);

        $mismatchMessage = $this->matcher->getMismatchMessage($first, $second);
        $expectedMessage = $this->buildSimpleExpectedMessage('Post fields', print_r($first->getPostFields(), true), print_r($second->getPostFields(), true));
        $this->assertEquals($mismatchMessage, $expectedMessage);
    }
}
