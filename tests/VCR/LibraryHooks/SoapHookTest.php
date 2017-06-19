<?php

namespace VCR\LibraryHooks;

use VCR\Request;
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

    /** @var  SoapHook $soapHook */
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

    public function testShouldHandleSOAPVersion11()
    {
        $expectedHeaders = array(
            'Content-Type' => 'text/xml; charset=utf-8;',
            'SOAPAction' => 'http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP',
        );
        $this->soapHook->enable($this->getHeadersCheckCallback($expectedHeaders));

        $client = new \SoapClient(
            'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL',
            array('soap_version' => SOAP_1_1)
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
    }

    public function testShouldHandleSOAPVersion12()
    {
        $expectedHeaders = array(
            'Content-Type' => 'application/soap+xml; charset=utf-8; action="http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP"',
        );

        $this->soapHook->enable($this->getHeadersCheckCallback($expectedHeaders));

        $client = new \SoapClient(
            'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL',
            array('soap_version' => SOAP_1_2)
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
    }

    public function testShouldReturnLastRequestWithTraceOn()
    {
        $this->soapHook->enable($this->getContentCheckCallback());

        $client = new \SoapClient(
            'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL',
            array('soap_version' => SOAP_1_1, 'trace' => 1)
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
        $actual = $client->__getLastRequest();

        $this->soapHook->disable();
        $this->assertTrue(!is_null($actual), '__getLastRequest() returned NULL.');
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
     * @param array $expectedHeaders
     * @return \callable
     */
    protected function getHeadersCheckCallback(array $expectedHeaders)
    {
        $test = $this;
        return function (Request $request) use ($test, $expectedHeaders) {
            foreach ($expectedHeaders as $expectedHeaderName => $expectedHeader) {
                $test->assertEquals($expectedHeader, $request->getHeader($expectedHeaderName));
            }
            return new Response(200, array(), '');
        };
    }
}
