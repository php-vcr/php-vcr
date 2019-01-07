<?php

namespace VCR\RequestMatchers;

use PHPUnit\Framework\TestCase;
use VCR\Request;

class PostFieldsMatcherTest extends TestCase
{
    public function testMatch()
    {
        $matcher = new PostFieldsMatcher();

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

        $this->assertTrue($matcher->match($first, $second));

        $mock['post_fields']['field2'] = 'changedvalue2';
        $third = Request::fromArray($mock);

        $this->assertFalse($matcher->match($first, $third));
    }
}
