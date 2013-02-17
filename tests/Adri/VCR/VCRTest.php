<?php

namespace Adri\VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class VCRTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->vcr = new VCR;
    }

    public function testHttpStreamWrapper()
    {
        $this->vcr->insertCassette('wrappertest');
        $result = file_get_contents('http://google.com');
        $this->assertNotEmpty($result);
    }

    public function testInsertMultipleCassettes()
    {
        $this->vcr->insertCassette('cassette1');
        $this->vcr->insertCassette('cassette2');

        $this->assertEquals('cassette2', $this->vcr->getCurrentCassette()->getName());
    }

    public function tearDown()
    {
        $this->vcr->turnOff();
    }
}
