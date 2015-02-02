<?php

namespace VCR;

use Symfony\Component\EventDispatcher\Event;
use org\bovigo\vfs\vfsStream;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class VCRTest extends \PHPUnit_Framework_TestCase
{

    public static function setupBeforeClass()
    {
       VCR::configure()->setCassettePath('tests/fixtures') ;
    }

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

    public function testShouldInterceptCurlLibrary()
    {
        VCR::configure()->enableLibraryHooks(array('curl'));
        VCR::turnOn();
        VCR::insertCassette('unittest_curl_test');

        $output = $this->doCurlGetRequest('http://google.com/');

        $this->assertEquals('This is a curl test dummy.', $output, 'Curl call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    private function doCurlGetRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, false);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
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
        $this->configureVirtualCassette();

        VCR::turnOn();
        VCR::insertCassette('unittest_cassette1');
        VCR::insertCassette('unittest_cassette2');
        // TODO: Check of cassette was changed
    }

    public function testDoesNotBlockThrowingExceptions()
    {
        $this->configureVirtualCassette();

        VCR::turnOn();
        $this->setExpectedException('InvalidArgumentException');
        VCR::insertCassette('unittest_cassette1');
        throw new \InvalidArgumentException('test');
    }

    private function configureVirtualCassette()
    {
        vfsStream::setup('testDir');
        VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testShouldSetAConfiguration()
    {
        VCR::configure()->setCassettePath('tests');
        VCR::turnOn();
        $this->assertEquals('tests', VCR::configure()->getCassettePath());
        VCR::turnOff();
    }

    public function testShouldDispatchBeforeAndAfterPlaybackWhenCassetteHasResponse()
    {
        VCR::configure()
            ->enableLibraryHooks(array('curl'));
        $this->recordAllEvents();
        VCR::turnOn();
        VCR::insertCassette('unittest_curl_test');

        $this->doCurlGetRequest('http://google.com/');

        $this->assertEquals(
            array(VCREvents::VCR_BEFORE_PLAYBACK, VCREvents::VCR_AFTER_PLAYBACK),
            $this->getRecordedEventNames()
        );
        VCR::eject();
        VCR::turnOff();

    }

    public function testShouldDispatchBeforeAfterHttpRequestAndBeforeRecordWhenCassetteHasNoResponse()
    {
        vfsStream::setup('testDir');
        VCR::configure()
            ->setCassettePath(vfsStream::url('testDir'))
            ->enableLibraryHooks(array('curl'));
        $this->recordAllEvents();
        VCR::turnOn();
        VCR::insertCassette('virtual_cassette');

        $this->doCurlGetRequest('http://google.com/');

        $this->assertEquals(
            array(VCREvents::VCR_BEFORE_HTTP_REQUEST, VCREvents::VCR_AFTER_HTTP_REQUEST, VCREvents::VCR_BEFORE_RECORD),
            $this->getRecordedEventNames()
        );
        VCR::eject();
        VCR::turnOff();
    }

    private function recordAllEvents()
    {
        $allEventsToListen = array(
            VCREvents::VCR_BEFORE_PLAYBACK,
            VCREvents::VCR_AFTER_PLAYBACK,
            VCREvents::VCR_BEFORE_HTTP_REQUEST,
            VCREvents::VCR_AFTER_HTTP_REQUEST,
            VCREvents::VCR_BEFORE_RECORD,
        );
        foreach ($allEventsToListen as $eventToListen) {
            VCR::getEventDispatcher()->addListener($eventToListen, array($this, 'recordEvent'));
        }
    }

    public function recordEvent(Event $event)
    {
        $this->events[$event->getName()] = $event;
    }

    private function getRecordedEventNames()
    {
        return array_keys($this->events);
    }
}
