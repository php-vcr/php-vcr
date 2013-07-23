<?php

namespace VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class RequestTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
    }

    public function testGetHeaders()
    {
        $this->assertEquals(
            array(
                'user-agent' => 'Unit-Test',
                'host'       => 'example.com'
            ),
            $this->request->getHeaders()
        );
    }

    public function testStorePostFields()
    {
        $this->request->addPostFields(array('para1' => 'val1'));
        $this->assertEquals(
            array(
                'method'      => 'GET',
                'url'         => 'http://example.com',
                'headers'     => array(
                    'user-agent' => 'Unit-Test',
                    'host' => 'example.com',
                    'content-type' => 'application/x-www-form-urlencoded; charset=utf-8'
                    ),
                'post_fields' => array('para1' => 'val1'),
            ),
            $this->request->toArray()
        );
    }

    public function tearDown()
    {
    }
}
