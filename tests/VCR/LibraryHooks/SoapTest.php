<?php

namespace VCR\LibraryHooks;

use VCR\Response;
use VCR\Configuration;
use VCR\LibraryHooks\Soap\Filter;
use VCR\Util\StreamProcessor;


/**
 * Test if intercepting http/https using soap works.
 */
class SoapTest extends \PHPUnit_Framework_TestCase
{
    public $expected = 'example response body';

    public function setup()
    {
        $this->config = new Configuration();
        $this->soapHook = new Soap(new Filter(), new StreamProcessor($this->config));
    }

    public function testShouldInterceptCallWhenEnabled()
    {
        $this->soapHook->enable($this->getTestCallback());

        $client = new \SoapClient('http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL', array('soap_version'   => SOAP_1_2));
        $response = $client->GetCityWeatherByZIP(array('ZIP' => '10013'));

        $this->soapHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not returned.');
    }

    public function testShouldNotInterceptCallWhenNotEnabled()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldNotInterceptCallWhenDisabled()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldWriteFileOnFileDownload()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldEchoResponseIfReturnTransferFalse()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldPostFieldsAsString()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldPostFieldsAsArray()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldReturnCurlInfoStatusCode()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldReturnCurlInfoAll()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldNotThrowErrorWhenDisabledTwice()
    {
        $this->markTestSkipped('not yet done');
    }

    public function testShouldNotThrowErrorWhenEnabledTwice()
    {
        $this->markTestSkipped('not yet done');
    }

    /**
     * @return \callable
     */
    protected function getTestCallback($handleRequestCallback = null)
    {
        $testClass = $this;
        return function($request) use($testClass) {
            return new Response(200, null, $testClass->expected);
        };
    }
}
