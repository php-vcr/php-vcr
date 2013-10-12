<?php

namespace VCR\Storage\Util;


use VCR\LibraryHooks\LibraryHooksException;
use VCR\Util\Soap\SoapClient;
use VCR\VCR_TestCase;

class SoapClientTest extends VCR_TestCase
{
    const WSDL = 'http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL';
    const ACTION = 'http://ws.cdyne.com/WeatherWS/GetCityWeatherByZIP';


    protected function getLibraryHookMock(array $methods =array())
    {
        return $this->getMockBuilder('\\VCR\\LibraryHooks\\Soap')
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }


    public function testDoRequest()
    {
        $expected = 'Knorx ist groß';

        $hook = $this->getLibraryHookMock();
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
            $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, 1)
        );
    }

    public function testDoRequestHookDisabled()
    {
        $hook = $this->getLibraryHookMock();
        $hook
            ->expects($this->once())
            ->method('doRequest')
            ->will(
                $this->throwException(
                    new LibraryHooksException(
                        'hook not enabled.',
                        LibraryHooksException::HookDisabled
                    )
                )
            );

        $client = new SoapClient(self::WSDL);
        $client->setLibraryHook($hook);

        $this->assertNull(
            $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, 1)
        );
    }

    public function testDoRequestExpectingException()
    {
        $exception = '\LogicException';

        $hook = $this->getLibraryHookMock();
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

        $client->__doRequest('Knorx ist groß', self::WSDL, self::ACTION, 1);
    }

    public function testLibraryHook()
    {
        $client = new SoapClient(self::WSDL);

        $this->assertInstanceOf('\\VCR\\LibraryHooks\\Soap', $client->getLibraryHook());


        $client->setLibraryHook($this->getLibraryHookMock());

        $this->assertInstanceOf('\\VCR\\LibraryHooks\\LibraryHookInterface', $client->getLibraryHook());
    }
}
