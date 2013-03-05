<?php

namespace VCR\LibraryHooks;

use \VCR\Response;

/**
 * Test if intercepting http/https using stream wrapper works.
 */
class CurlTest extends \PHPUnit_Framework_TestCase
{
    public $expected = 'example response body';

    public function testShouldInterceptCallWhenEnabled()
    {
        $curlHook = $this->createCurl();
        $curlHook->enable();

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $actual = curl_exec($ch);
        curl_close($ch);

        $curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not returned.');
    }

    public function testShouldNotInterceptCallWhenNotEnabled()
    {
        $testClass = $this;
        $curlHook = $this->createCurl(function($request) use($testClass) {
            $testClass->fail('This request should not have been intercepted.');
        });

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function testShouldNotInterceptCallWhenDisabled()
    {
        $testClass = $this;
        $curlHook = $this->createCurl(function($request) use($testClass) {
            $testClass->fail('This request should not have been intercepted.');
        });
        $curlHook->enable();
        $curlHook->disable();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function testShouldWriteFileOnFileDownload()
    {
        $curlHook = $this->createCurl();
        $curlHook->enable();

        $ch = curl_init('https://127.0.0.1/');
        $fp = fopen('php://temp/test_file', 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        rewind($fp);
        $actual = fread($fp, 1024);
        fclose($fp);

        $curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written in file.');
    }

    public function testShouldEchoResponseIfReturnTransferFalse()
    {
        $curlHook = $this->createCurl();
        $curlHook->enable();

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        ob_start();
        curl_exec($ch);
        $actual = ob_get_contents();
        ob_end_clean();
        curl_close($ch);

        $curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written on stdout.');
    }

    public function testShouldPostFieldsAsString()
    {
        $testClass = $this;
        $curlHook = $this->createCurl(function($request) use($testClass) {
            $testClass->assertEquals(
                array('para1' => 'val1', 'para2' => 'val2'),
                $request->getPostFields()->getAll(),
                'Post query string was not parsed and set correctly.'
            );
            return new Response(200);
        });
        $curlHook->enable();

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'para1=val1&para2=val2');
        curl_exec($ch);
        curl_close($ch);
        $curlHook->disable();
    }

    public function testShouldPostFieldsAsArray()
    {
        $testClass = $this;
        $curlHook = $this->createCurl(function($request) use($testClass) {
            $testClass->assertEquals(
                array('para1' => 'val1', 'para2' => 'val2'),
                $request->getPostFields()->getAll(),
                'Post query string was not parsed and set correctly.'
            );
            return new Response(200);
        });
        $curlHook->enable();

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('para1' => 'val1', 'para2' => 'val2'));
        curl_exec($ch);
        curl_close($ch);
        $curlHook->disable();
    }

    public function testShouldReturnCurlInfoStatusCode()
    {
        $curlHook = $this->createCurl();
        $curlHook->enable();

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        $this->assertEquals(200, curl_getinfo($ch, CURLINFO_HTTP_CODE), 'HTTP status not set.');
        $curlHook->disable();
    }

    public function testShouldReturnCurlInfoAll()
    {
        $curlHook = $this->createCurl();
        $curlHook->enable();

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        $this->assertTrue(is_array(curl_getinfo($ch)), 'curl_getinfo() should return an array.');
        $this->assertEquals(19, count(curl_getinfo($ch)), 'curl_getinfo() should return 19 values.');
        $curlHook->disable();
    }

    public function testShouldNotThrowErrorWhenDisabledTwice()
    {
        $curlHook = $this->createCurl();
        $curlHook->disable();
        $curlHook->disable();
    }

    public function testShouldNotThrowErrorWhenEnabledTwice()
    {
        $curlHook = $this->createCurl();
        $curlHook->enable();
        $curlHook->enable();
    }

    /**
     * @return \VCR\LibraryHooks\Curl
     */
    private function createCurl($handleRequestCallback = null)
    {
        if (is_null($handleRequestCallback)) {
            $testClass = $this;
            $handleRequestCallback = function($request) use($testClass) {
                return new Response(200, null, $testClass->expected);
            };
        }
        return new Curl($handleRequestCallback);
    }
}
