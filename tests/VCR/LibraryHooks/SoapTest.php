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

        $client = new \SoapClient('http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL', array('soap_version' => SOAP_1_2));
        $actual = $client->GetCityWeatherByZIP(array('ZIP' => '10013'));

        $this->soapHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not returned.');
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
