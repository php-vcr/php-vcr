<?php

namespace Adri\VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class RequestTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
    }

    public function testGetHeaders()
    {
        $this->assertEquals(
            array(
                'User-Agent' => 'Unit-Test',
                'Host'       => 'example.com'
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
                'url'         => 'http://example.com/',
                'headers'     => array(
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                    ),
                'body'        => null,
                'post_files'  => array(),
                'post_fields' => array('para1' => 'val1'),
            ),
            $this->request->toArray()
        );
    }

    public function tearDown()
    {
    }
}
