<?php

namespace VCR;


/**
 * Test integration of PHPVCR with PHPUnit.
 */
class VCRTest extends VCR_TestCase
{

    public function testUseStaticCallsNotInitialized()
    {
        VCR::configure()->enableLibraryHooks(array('stream_wrapper'));
        $this->setExpectedException(
            'VCR\VCRException',
            'Please turn on VCR before inserting a cassette, use: VCR::turnOn()'
        );
        VCR::insertCassette('some_name');
    }

    public function testShouldInterceptStreamWrapper()
    {
        VCR::configure()->enableLibraryHooks(array('stream_wrapper'));
        VCR::turnOn();
        VCR::insertCassette('unittest_streamwrapper_test');
        $result = file_get_contents('http://example.com');
        $this->assertEquals('This is a stream wrapper test dummy.', $result, 'Stream wrapper call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @group runkit
     */
    public function testShouldInterceptCurl()
    {
        $this->skipTestIfRunkitUnavailable();
        VCR::configure()->enableLibraryHooks(array('curl_runkit'));
        VCR::turnOn();
        VCR::insertCassette('unittest_curl_test');
        $ch = curl_init('http://example.com/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        $this->assertEquals('This is a curl test dummy.', $result, 'Curl call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    public function testShouldInterceptGuzzleLibrary()
    {
        $this->markTestSkipped('Not yet implemented.');
        VCR::configure()->enableLibraryHooks(array('curl_rewrite'));
        VCR::turnOn();
        VCR::insertCassette('unittest_guzzle_test');
        $client = new \Guzzle\Http\Client();
        $response = $client->get('http://example.com')->send();
        $this->assertEquals('This is a guzzle test dummy.', (string) $response->getBody(), 'Guzzle call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    public function testShouldInterceptSoapLibrary()
    {
        VCR::configure()->enableLibraryHooks(array('soap'));
        VCR::turnOn();
        VCR::insertCassette('unittest_soap_test');

        $client = new \SoapClient('http://wsf.cdyne.com/WeatherWS/Weather.asmx?WSDL', array('soap_version' => SOAP_1_2));
        $actual = $client->GetCityWeatherByZIP(array('ZIP' => '10013'));
        $temperature = $actual->GetCityWeatherByZIPResult->Temperature;

        $this->assertEquals('1337', $temperature, 'Soap call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    public function testShouldThrowExceptionIfNoCassettePresent()
    {
        $this->setExpectedException(
            'BadMethodCallException',
            "Invalid http request. No cassette inserted. Please make sure to insert "
            . "a cassette in your unit test using VCR::insertCassette('name');"
        );

        VCR::configure()->enableLibraryHooks(array('stream_wrapper'));
        VCR::turnOn();
        // If there is no cassette inserted, a request should throw an exception
        file_get_contents('http://example.com');
        VCR::turnOff();
    }

    public function testInsertMultipleCassettes()
    {
        $this->markTestSkipped();
        VCR::turnOn();
        VCR::insertCassette('unittest_cassette1');
        VCR::insertCassette('unittest_cassette2');
        // TODO: Check of cassette was changed
    }

    public function testDoesNotBlockThrowingExceptions()
    {
        VCR::turnOn();
        $this->setExpectedException('InvalidArgumentException');
        VCR::insertCassette('unittest_cassette1');
        throw new \InvalidArgumentException('test');
    }

    public function testShouldSetAConfiguration()
    {
        VCR::configure()->setCassettePath('tests');
        VCR::turnOn();
        $this->assertEquals('tests', VCR::configure()->getCassettePath());
        VCR::turnOff();
    }
}
