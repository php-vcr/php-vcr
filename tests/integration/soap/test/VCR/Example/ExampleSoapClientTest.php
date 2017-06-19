<?php

namespace VCR\Example;

use org\bovigo\vfs\vfsStream;

/**
 * Converts temperature units from webservicex
 *
 * @link http://www.webservicex.net/New/Home/ServiceDetail/31
 */
class ExampleSoapClientTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // Configure virtual filesystem.
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testCallDirectly()
    {
        $actual = $this->callSoap();
        $this->assertInternalType('integer', $actual);
    }

    public function testCallIntercepted()
    {
        $actual = $this->callSoapIntercepted();
        $this->assertInternalType('integer', $actual);
    }

    public function testCallDirectlyEqualsIntercepted()
    {
        $this->assertEquals($this->callSoap(), $this->callSoapIntercepted());
    }

    protected function callSoap()
    {
        $soapClient = new ExampleSoapClient();
        return $soapClient->call('10013'); // somewhere in New York
    }

    protected function callSoapIntercepted()
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $result = $this->callSoap();
        \VCR\VCR::turnOff();

        return $result;
    }
}
