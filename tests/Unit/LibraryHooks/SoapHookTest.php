<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\LibraryHooks;

use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\SoapCodeTransform;
use VCR\Configuration;
use VCR\LibraryHooks\SoapHook;
use VCR\Request;
use VCR\Response;
use VCR\Util\StreamProcessor;

final class SoapHookTest extends TestCase
{
    public const WSDL = 'https://raw.githubusercontent.com/php-vcr/php-vcr/master/tests/fixtures/soap/wsdl/weather.wsdl';

    /** @var string */
    public $expected = '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><GetCityWeatherByZIPResponse xmlns="http://ws.cdyne.com/WeatherWS/"><GetCityWeatherByZIPResult><Success>true</Success></GetCityWeatherByZIPResult></GetCityWeatherByZIPResponse></soap:Body></soap:Envelope>';

    protected Configuration $config;

    protected SoapHook $soapHook;

    protected function setup(): void
    {
        $this->config = new Configuration();
        $this->soapHook = new SoapHook(new SoapCodeTransform(), new StreamProcessor($this->config));
    }

    public function testShouldInterceptCallWhenEnabled(): void
    {
        $this->soapHook->enable($this->getContentCheckCallback());

        /** @var \VCR\Util\SoapClient $client */
        $client = new \SoapClient(self::WSDL, ['soap_version' => \SOAP_1_2]);
        $client->setLibraryHook($this->soapHook);
        $actual = $client->GetCityWeatherByZIP(['ZIP' => '10013']);

        $this->soapHook->disable();
        $this->assertInstanceOf('\stdClass', $actual, 'Response was not returned.');
        $this->assertTrue($actual->GetCityWeatherByZIPResult->Success, 'Response was not returned.');
    }

    public function testShouldHandleSOAPVersion11(): void
    {
        $expectedHeaders = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => 'http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP',
        ];
        $this->soapHook->enable($this->getHeadersCheckCallback($expectedHeaders));

        /** @var \VCR\Util\SoapClient $client */
        $client = new \SoapClient(
            self::WSDL,
            ['soap_version' => \SOAP_1_1]
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(['ZIP' => '10013']);
    }

    public function testShouldHandleSOAPVersion12(): void
    {
        $expectedHeaders = [
            'Content-Type' => 'application/soap+xml; charset=utf-8; action="http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP"',
        ];

        $this->soapHook->enable($this->getHeadersCheckCallback($expectedHeaders));

        /** @var \VCR\Util\SoapClient $client */
        $client = new \SoapClient(
            self::WSDL,
            ['soap_version' => \SOAP_1_2]
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(['ZIP' => '10013']);
    }

    public function testShouldReturnLastRequestWithTraceOn(): void
    {
        $this->soapHook->enable($this->getContentCheckCallback());

        /** @var \VCR\Util\SoapClient $client */
        $client = new \SoapClient(
            self::WSDL,
            ['soap_version' => \SOAP_1_1, 'trace' => 1]
        );
        $client->setLibraryHook($this->soapHook);
        $client->GetCityWeatherByZIP(['ZIP' => '10013']);
        $actual = $client->__getLastRequest();

        $this->soapHook->disable();
        $this->assertNotNull($actual, '__getLastRequest() returned NULL.');
    }

    protected function getContentCheckCallback(): \Closure
    {
        $testClass = $this;

        return \Closure::fromCallable(fn () => new Response('200', [], $testClass->expected));
    }

    /** @param array<mixed> $expectedHeaders */
    protected function getHeadersCheckCallback(array $expectedHeaders): \Closure
    {
        $test = $this;

        return \Closure::fromCallable(function (Request $request) use ($test, $expectedHeaders) {
            foreach ($expectedHeaders as $expectedHeaderName => $expectedHeader) {
                $test->assertEquals($expectedHeader, $request->getHeader($expectedHeaderName));
            }

            return new Response('200', [], '');
        });
    }
}
