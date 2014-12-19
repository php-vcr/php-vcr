<?php

namespace VCR\LibraryHooks;

use VCR\Response;
use VCR\Configuration;
use VCR\CodeTransform\SoapCodeTransform;
use VCR\Util\StreamProcessor;


/**
 * Test if intercepting http/https using soap works.
 */
class SoapHookTest extends \PHPUnit_Framework_TestCase
{
    public $expected = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><GetCityWeatherByZIPResponse xmlns="http://ws.cdyne.com/WeatherWS/"><GetCityWeatherByZIPResult><Success>true</Success></GetCityWeatherByZIPResult></GetCityWeatherByZIPResponse></soap:Body></soap:Envelope>';

    protected $config;

    /** @var  Soap $soapHook */
    protected $soapHook;

    public function setup()
    {
        $this->config = new Configuration();
        $this->soapHook = new SoapHook(new SoapCodeTransform(), new StreamProcessor($this->config));
    }


    public function testShouldInterceptCallWhenEnabled()
    {
        $this->soapHook->enable($this->getContentCheckCallback());

        $client = new \SoapClient('http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL', array('soap_version' => SOAP_1_2));
        $client->setLibraryHook($this->soapHook);
        $actual = $client->GetCityWeatherByZIP(array('ZIP' => '10013'));

        $this->soapHook->disable();
        $this->assertInstanceOf('\stdClass', $actual, 'Response was not returned.');
        $this->assertEquals(true, $actual->GetCityWeatherByZIPResult->Success, 'Response was not returned.');
    }

    /**
     * @group uses_internet
     */
    public function testShouldNotInterceptCallWhenDisabled()
    {
        $this->soapHook->disable();

        $client = new \SoapClient('http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL', array('soap_version' => SOAP_1_2));
        $client->setLibraryHook($this->soapHook);

        $actual = $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
        $this->assertInstanceOf('\stdClass', $actual, 'Response was not returned.');
    }

    public function testShouldHandleSOAPVersion11()
    {
        $expectedHeader = 'text/xml; charset=utf-8; action="http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP"';
        $this->soapHook->enable($this->getHeaderCheckCallback($expectedHeader));

        $client = new \SoapClient(
            'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL',
            array('soap_version' => SOAP_1_1)
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
    }

    public function testShouldHandleSOAPVersion12()
    {
        $expectedHeader = 'application/soap+xml; charset=utf-8; action="http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP"';
        $this->soapHook->enable($this->getHeaderCheckCallback($expectedHeader));

        $client = new \SoapClient(
            'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL',
            array('soap_version' => SOAP_1_2)
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
    }

    /**
     * @return \callable
     */
    protected function getContentCheckCallback()
    {
        $testClass = $this;
        return function () use ($testClass) {
            return new Response(200, array(), $testClass->expected);
        };
    }

    /**
     * @param string $expectedHeader
     * @return \callable
     */
    protected function getHeaderCheckCallback($expectedHeader)
    {
        $test = $this;
        return function ($request) use ($test, $expectedHeader) {
            $test->assertEquals($expectedHeader, $request->getHeader('Content-Type'));
            return new Response(200, array(), '');
        };
    }
}
