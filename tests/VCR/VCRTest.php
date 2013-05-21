<?php

namespace VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class VCRTest extends \PHPUnit_Framework_TestCase
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
        $result = file_get_contents('http://google.com');
        $this->assertEquals('This is a stream wrapper test dummy.', $result, 'Stream wrapper call was not intercepted.');
        VCR::eject();
    }

    /**
     * @group runkit
     */
    public function testShouldInterceptCurl()
    {
        VCR::configure()->enableLibraryHooks(array('curl'));
        VCR::turnOn();
        VCR::insertCassette('unittest_curl_test');
        $ch = curl_init('http://google.com/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        $this->assertEquals('This is a curl test dummy.', $result, 'Curl call was not intercepted.');
        VCR::eject();
    }

    /**
     * @group runkit
     */
    public function testShouldInterceptGuzzleLibrary()
    {
        $this->markTestSkipped('Not working yet.');
        VCR::turnOn();
        VCR::insertCassette('unittest_guzzle_test');
        $client = new \Guzzle\Http\Client();
        $response = $client->get('http://google.com')->send();
        $this->assertEquals('This is a guzzle test dummy.', (string) $response->getBody(), 'Guzzle call was not intercepted.');
        VCR::eject();
    }

    public function testShouldThrowExceptionIfNoCasettePresent()
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
    }

    /**
     * @group runkit
     */
    public function testInsertMultipleCassettes()
    {
        $this->markTestSkipped();
        VCR::turnOn();
        VCR::insertCassette('unittest_cassette1');
        VCR::insertCassette('unittest_cassette2');
        // TODO: Check of cassette was changed
    }

    /**
     * @group runkit
     */
    public function testThrowExeptions()
    {
        VCR::turnOn();
        $this->setExpectedException('InvalidArgumentException');
        VCR::insertCassette('unittest_cassette1');
        throw new \InvalidArgumentException('test');
    }

    /**
     * @group runkit
     */
    public function testShouldSetAConfiguration()
    {
        VCR::configure()->setCassettePath('tests');
        VCR::turnOn();
        $this->assertEquals('tests', VCR::configure()->getCassettePath());
    }

    // public function test()
    // {
    //     VCRFactory::returnOn('VCR\Storage\Json', $storageMock);
    //     VCR::turnOn();
    // }

}
