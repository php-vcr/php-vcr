<?php

namespace VCR\Util;

use lapistano\ProxyObject\ProxyBuilder;
use VCR\VCRException;
use VCR\Util\SoapClient;
use PHPUnit\Framework\TestCase;

class SoapClientTest extends TestCase
{
    const WSDL = 'https://raw.githubusercontent.com/php-vcr/php-vcr/master/tests/fixtures/soap/wsdl/weather.wsdl';
    const ACTION = 'http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP';

    protected function getLibraryHookMock($enabled)
    {
        $hookMock = $this->getMockBuilder('\VCR\LibraryHooks\SoapHook')
            ->disableOriginalConstructor()
            ->setMethods(array('isEnabled', 'doRequest'))
            ->getMock();

        $hookMock
            ->expects($this->any())
            ->method('isEnabled')
            ->will($this->returnValue($enabled));

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
            ->will($this->returnValue($expected));

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
        $hook->expects($this->once())->method('doRequest')->will($this->returnValue('some value'));

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $this->assertNull($client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, SOAP_1_2, 1));
    }

    public function testDoRequestOneWayDisabled()
    {
        $expected = 'some value';
        $hook = $this->getLibraryHookMock(true);
        $hook ->expects($this->once()) ->method('doRequest')->will($this->returnValue($expected));

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
            ->setMethods(array('realDoRequest'))
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

        $this->setExpectedException($exception);

        $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, SOAP_1_2);
    }

    public function testLibraryHook()
    {
        $client = new SoapClient(self::WSDL);

        $proxy = new ProxyBuilder('\VCR\Util\SoapClient');
        $client = $proxy
            ->disableOriginalConstructor()
            ->setMethods(array('getLibraryHook'))
            ->getProxy();

        $this->assertInstanceOf('\VCR\LibraryHooks\SoapHook', $client->getLibraryHook());

        $client->setLibraryHook($this->getLibraryHookMock(true));

        $this->assertInstanceOf('\VCR\LibraryHooks\SoapHook', $client->getLibraryHook());
    }

    public function testGetLastWhateverBeforeRequest()
    {
        $client = new SoapClient(self::WSDL);

        $this->assertNull($client->__getLastRequest());
        $this->assertNull($client->__getLastResponse());
    }

    public function testGetLastWhateverAfterRequest()
    {
        $request  = 'Knorx ist groß';
        $response = 'some value';

        $hook = $this->getLibraryHookMock(true);
        $hook->expects($this->once())->method('doRequest')->will($this->returnValue($response));

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $client->__doRequest($request, self::WSDL, self::ACTION, SOAP_1_2, 0);

        $this->assertEquals($request, $client->__getLastRequest());
        $this->assertEquals($response, $client->__getLastResponse());
    }
}
