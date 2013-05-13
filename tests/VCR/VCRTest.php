<?php

namespace VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class VCRTest extends \PHPUnit_Framework_TestCase
{
    public function testUseStaticCallsNotInitialized()
    {
        $this->setExpectedException('\BadMethodCallException');
        VCR::useCassette('some_name');
    }

    public function testShouldInterceptStreamWrapper()
    {
        $config = new Configuration();
        $config->enableLibraryHooks(array('stream_wrapper'));
        VCR::init($config);
        VCR::useCassette('unittest_streamwrapper_test');
        $result = file_get_contents('http://google.com');
        $this->assertEquals('This is a stream wrapper test dummy.', $result, 'Stream wrapper call was not intercepted.');
        VCR::eject();
    }

    /**
     * @group runkit
     */
    public function testShouldInterceptCurl()
    {
        VCR::init();
        VCR::useCassette('unittest_curl_test');
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
        VCR::init();
        VCR::useCassette('unittest_guzzle_test');
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
            . "a cassette in your unit test using VCR::useCassette('name');"
        );
        VCR::init();
        // If there is no cassette inserted, a request should throw an exception
        file_get_contents('http://example.com');
    }

    /**
     * @group runkit
     */
    public function testInsertMultipleCassettes()
    {
        $this->markTestSkipped();
        VCR::init();
        VCR::useCassette('unittest_cassette1');
        VCR::useCassette('unittest_cassette2');

        // $this->assertEquals('cassette2', VCR::get()->getName());
    }

    /**
     * @group runkit
     */
    public function testThrowExeptions()
    {
        VCR::init();
        $this->setExpectedException('InvalidArgumentException');
        VCR::useCassette('unittest_cassette1');
        throw new \InvalidArgumentException('test');
    }

    /**
     * @group runkit
     */
    public function testShouldSetAConfiguration()
    {
        $config = new Configuration();
        $config->setCassettePath('tests');
        VCR::init($config);
        $this->assertEquals('tests', VCR::getInstance()->getConfiguration()->getCassettePath());
    }
}
