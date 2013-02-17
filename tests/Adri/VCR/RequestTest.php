<?php

namespace Adri\VCR;


/**
 * Test integration of PHPVCR with PHPUnit.
 */
class RequsetTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
    }

    public function testGetHeaders()
    {
        $this->assertEquals(
            array(
                'User-Agent' => array('Unit-Test'),
                'Host'       => array('example.com')
            ),
            $this->request->getHeaders()
        );
    }

    public function tearDown()
    {
    }
}
