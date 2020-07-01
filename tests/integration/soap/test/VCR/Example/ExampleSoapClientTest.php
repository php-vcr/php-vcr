<?php

namespace VCR\Example;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use SoapFault;

/**
 * Converts temperature units from webservicex.
 *
 * @see http://www.webservicex.net/New/Home/ServiceDetail/31
 */
class ExampleSoapClientTest extends TestCase
{
    public function setUp()
    {
        // Configure virtual filesystem.
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));

        // Trigger autoload of assertions (broken after VCR is enabled...
        $this->assertNotNull(1);
        $this->assertTrue(true);
    }

    public function testCallDirectly()
    {
        $actual = $this->callSoap();
        $this->assertInternalType('string', $actual);
        $this->assertEquals('twelve', $actual);
    }

    public function testCallIntercepted()
    {
        $actual = $this->callSoapIntercepted();
        $this->assertInternalType('string', $actual);
        $this->assertEquals('twelve', $actual);
    }

    public function testCallDirectlyEqualsIntercepted()
    {
        $this->assertEquals($this->callSoap(), $this->callSoapIntercepted());
    }

    /**
     * This test performs a SOAP request on a buggy WSDL.
     * It checks that the non instrumented code and the instrumented code return the same exception.
     */
    public function testCallSoapWithError()
    {
        $nonInstrumentedException = null;
        try {
            $soapClient = new ExampleSoapClient();
            $soapClient->callBadUrl();
        } catch (SoapFault $e) {
            $nonInstrumentedException = $e;
        }
        $this->assertNotNull($nonInstrumentedException);
        $catched = false;
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        try {
            $soapClient = new ExampleSoapClient();
            $soapClient->callBadUrl();
        } catch (SoapFault $e) {
            $catched = true;
            $this->assertEquals($e->getMessage(), $nonInstrumentedException->getMessage());
        }
        $this->assertTrue($catched);
        \VCR\VCR::turnOff();
    }

    protected function callSoap()
    {
        $soapClient = new ExampleSoapClient();

        return $soapClient->call(12);
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
