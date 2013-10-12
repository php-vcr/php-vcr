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

    protected $config;

    /** @var  Soap $soapHook */
    protected $soapHook;

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
        $this->assertInstanceOf('\stdClass', $actual, 'Response was not returned.');
    }

    public function testShouldNotInterceptCallWhenDisabled()
    {
        $client = new \SoapClient('http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL', array('soap_version' => SOAP_1_2));

        $this->soapHook->disable();

        $actual = $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
        $this->assertInstanceOf('\stdClass', $actual, 'Response was not returned.');
    }

    /**
     * @param null $handleRequestCallback
     *
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
