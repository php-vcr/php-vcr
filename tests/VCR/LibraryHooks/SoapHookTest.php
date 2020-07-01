<?php

namespace VCR\LibraryHooks;

use Closure;
use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\SoapCodeTransform;
use VCR\Configuration;
use VCR\Request;
use VCR\Response;
use VCR\Util\StreamProcessor;

/**
 * Test if intercepting http/https using soap works.
 */
class SoapHookTest extends TestCase
{
    const WSDL = 'https://raw.githubusercontent.com/php-vcr/php-vcr/master/tests/fixtures/soap/wsdl/weather.wsdl';

    public $expected = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><GetCityWeatherByZIPResponse xmlns="http://ws.cdyne.com/WeatherWS/"><GetCityWeatherByZIPResult><Success>true</Success></GetCityWeatherByZIPResult></GetCityWeatherByZIPResponse></soap:Body></soap:Envelope>';

    protected $config;

    /** @var SoapHook */
    protected $soapHook;

    public function setup()
    {
        $this->config = new Configuration();
        $this->soapHook = new SoapHook(new SoapCodeTransform(), new StreamProcessor($this->config));
    }

    public function testShouldInterceptCallWhenEnabled()
    {
        $this->soapHook->enable($this->getContentCheckCallback());

        $client = new \SoapClient(self::WSDL, ['soap_version' => SOAP_1_2]);
        $client->setLibraryHook($this->soapHook);
        $actual = $client->GetCityWeatherByZIP(['ZIP' => '10013']);

        $this->soapHook->disable();
        $this->assertInstanceOf('\stdClass', $actual, 'Response was not returned.');
        $this->assertTrue($actual->GetCityWeatherByZIPResult->Success, 'Response was not returned.');
    }

    public function testShouldHandleSOAPVersion11()
    {
        $expectedHeaders = [
            'Content-Type' => 'text/xml; charset=utf-8;',
            'SOAPAction' => 'http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP',
        ];
        $this->soapHook->enable($this->getHeadersCheckCallback($expectedHeaders));

        $client = new \SoapClient(
            self::WSDL,
            ['soap_version' => SOAP_1_1]
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(['ZIP' => '10013']);
    }

    public function testShouldHandleSOAPVersion12()
    {
        $expectedHeaders = [
            'Content-Type' => 'application/soap+xml; charset=utf-8; action="http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP"',
        ];

        $this->soapHook->enable($this->getHeadersCheckCallback($expectedHeaders));

        $client = new \SoapClient(
            self::WSDL,
            ['soap_version' => SOAP_1_2]
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(['ZIP' => '10013']);
    }

    public function testShouldReturnLastRequestWithTraceOn()
    {
        $this->soapHook->enable($this->getContentCheckCallback());

        $client = new \SoapClient(
            self::WSDL,
            ['soap_version' => SOAP_1_1, 'trace' => 1]
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(['ZIP' => '10013']);
        $actual = $client->__getLastRequest();

        $this->soapHook->disable();
        $this->assertNotNull($actual, '__getLastRequest() returned NULL.');
    }

    protected function getContentCheckCallback(): Closure
    {
        $testClass = $this;

        return Closure::fromCallable(function () use ($testClass) {
            return new Response(200, [], $testClass->expected);
        });
    }

    protected function getHeadersCheckCallback(array $expectedHeaders): Closure
    {
        $test = $this;

        return Closure::fromCallable(function (Request $request) use ($test, $expectedHeaders) {
            foreach ($expectedHeaders as $expectedHeaderName => $expectedHeader) {
                $test->assertEquals($expectedHeader, $request->getHeader($expectedHeaderName));
            }

            return new Response(200, [], '');
        });
    }
}
