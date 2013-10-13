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
    public $expected = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><GetCityWeatherByZIPResponse xmlns="http://ws.cdyne.com/WeatherWS/"><GetCityWeatherByZIPResult><Success>true</Success></GetCityWeatherByZIPResult></GetCityWeatherByZIPResponse></soap:Body></soap:Envelope>';

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
        $client->setLibraryHook($this->soapHook);
        $actual = $client->GetCityWeatherByZIP(array('ZIP' => '10013'));

        $this->soapHook->disable();
        $this->assertInstanceOf('\stdClass', $actual, 'Response was not returned.');
        $this->assertEquals(true, $actual->GetCityWeatherByZIPResult->Success, 'Response was not returned.');
    }

    public function testShouldNotInterceptCallWhenDisabled()
    {
        $this->markTestSkipped('Uses internet connection, find another way to test this.');
        $this->soapHook->disable();

        $client = new \SoapClient('http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL', array('soap_version' => SOAP_1_2));
        $client->setLibraryHook($this->soapHook);

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
