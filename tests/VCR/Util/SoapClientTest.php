<?php

namespace VCR\Util;

use PHPUnit\Framework\TestCase;
use VCR\LibraryHooks\SoapHook;

class SoapClientTest extends TestCase
{
    const WSDL = 'https://raw.githubusercontent.com/php-vcr/php-vcr/master/tests/fixtures/soap/wsdl/weather.wsdl';
    const ACTION = 'http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP';

    protected function getLibraryHookMock($enabled)
    {
        $hookMock = $this->getMockBuilder('\VCR\LibraryHooks\SoapHook')
            ->disableOriginalConstructor()
            ->setMethods(['isEnabled', 'doRequest'])
            ->getMock();

        $hookMock
            ->expects($this->any())
            ->method('isEnabled')
            ->willReturn($enabled);

        return $hookMock;
    }

    public function testDoRequest()
    {
        $expected = 'Knorx ist groß';

        $hook = $this->getLibraryHookMock(true);
        $hook
            ->expects($this->once())
            ->method('doRequest')
            ->with(
                $this->isType('string'),
                $this->isType('string'),
                $this->isType('string'),
                $this->isType('integer')
            )
            ->willReturn($expected);

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $this->assertEquals(
            $expected,
            $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, SOAP_1_2)
        );
    }

    public function testDoRequestOneWayEnabled()
    {
        $hook = $this->getLibraryHookMock(true);
        $hook->expects($this->once())->method('doRequest')->willReturn('some value');

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $this->assertNull($client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, SOAP_1_2, 1));
    }

    public function testDoRequestOneWayDisabled()
    {
        $expected = 'some value';
        $hook = $this->getLibraryHookMock(true);
        $hook->expects($this->once())->method('doRequest')->willReturn($expected);

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $this->assertEquals(
            $expected,
            $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, SOAP_1_2, 0)
        );
    }

    public function testDoRequestHandlesHookDisabled()
    {
        $client = $this->getMockBuilder('\VCR\Util\SoapClient')
            ->disableOriginalConstructor()
            ->setMethods(['realDoRequest'])
            ->getMock();

        $client
            ->expects($this->once())
            ->method('realDoRequest')
            ->with(
                $this->equalTo('Knorx ist groß'),
                $this->equalTo(self::WSDL),
                $this->equalTo(self::ACTION),
                $this->equalTo(SOAP_1_2),
                $this->equalTo(0)
            );

        $hook = $this->getLibraryHookMock(false);
        $client->setLibraryHook($hook);

        $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, SOAP_1_2);
    }

    public function testDoRequestExpectingException()
    {
        $exception = '\LogicException';

        $hook = $this->getLibraryHookMock(true);
        $hook
            ->expects($this->once())
            ->method('doRequest')
            ->will(
                $this->throwException(
                    new \LogicException('hook not enabled.')
                )
            );

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $this->expectException($exception);

        $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, SOAP_1_2);
    }

    public function testLibraryHook()
    {
        $client = new class(self::WSDL) extends SoapClient {
            // A proxy to access the protected getLibraryHook method.
            public function publicGetLibraryHook(): SoapHook
            {
                return $this->getLibraryHook();
            }
        };

        $this->assertInstanceOf('\VCR\LibraryHooks\SoapHook', $client->publicGetLibraryHook());

        $client->setLibraryHook($this->getLibraryHookMock(true));

        $this->assertInstanceOf('\VCR\LibraryHooks\SoapHook', $client->publicGetLibraryHook());
    }

    public function testGetLastWhateverBeforeRequest()
    {
        $client = new SoapClient(self::WSDL);

        $this->assertNull($client->__getLastRequest());
        $this->assertNull($client->__getLastResponse());
    }

    public function testGetLastWhateverAfterRequest()
    {
        $request = 'Knorx ist groß';
        $response = 'some value';

        $hook = $this->getLibraryHookMock(true);
        $hook->expects($this->once())->method('doRequest')->willReturn($response);

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $client->__doRequest($request, self::WSDL, self::ACTION, SOAP_1_2, 0);

        $this->assertEquals($request, $client->__getLastRequest());
        $this->assertEquals($response, $client->__getLastResponse());
    }
}
