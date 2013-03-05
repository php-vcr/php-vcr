<?php

namespace VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class VCRTest extends \PHPUnit_Framework_TestCase
{

    public function testOneStreamWrapper()
    {
        $this->vcr = new VCR;
        $this->vcr->insertCassette('wrappertest');
        $result = file_get_contents('http://127.0.0.1');
        $this->assertNotEmpty($result);
    }

    public function testInsertMultipleCassettes()
    {
        $this->vcr = new VCR;
        $this->vcr->insertCassette('cassette1');
        $this->vcr->insertCassette('cassette2');

        $this->assertEquals('cassette2', $this->vcr->getCurrentCassette()->getName());
    }

    public function testThrowExeptions()
    {
        $this->vcr = new VCR;
        $this->setExpectedException('InvalidArgumentException');
        $this->vcr->insertCassette('cassette1');
        throw new \InvalidArgumentException('test');
    }

    public function testUseStaticCallsNotInitialized()
    {
        $this->setExpectedException('\BadMethodCallException');
        VCR::useCassette('some_name');
    }

    public function testUseStaticCallsUseCassette()
    {
        VCR::init();
        VCR::useCassette('some_name');
        $this->assertEquals('some_name', VCR::getInstance()->getCurrentCassette()->getName());
    }

    public function testUseStaticCallsSetConfiguration()
    {
        VCR::init()->setCassettePath('tests');
        $this->assertEquals('tests', VCR::getInstance()->getConfiguration()->getCassettePath());
    }

    public function tearDown()
    {
        if (isset($this->vcr)) {
            $this->vcr->turnOff();
        } else if (VCR::getInstance()) {
            VCR::getInstance()->turnOff();
        }
    }
}
