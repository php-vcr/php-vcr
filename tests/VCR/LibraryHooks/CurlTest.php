<?php

namespace VCR\LibraryHooks;

use VCR\Response;
use VCR\Configuration;
use VCR\Filter\CurlFilter;
use VCR\Util\StreamProcessor;

/**
 * Test if intercepting http/https using curl works.
 */
class CurlTest extends \PHPUnit_Framework_TestCase
{
    public $expected = 'example response body';

    public function setup()
    {
        $this->config = new Configuration();
        $this->curlHook = new Curl(new CurlFilter(), new StreamProcessor($this->config));
    }

    public function testShouldInterceptCallWhenEnabled()
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com/');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $actual = curl_exec($curlHandle);
        curl_close($curlHandle);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not returned.');
    }

    public function testShouldNotInterceptCallWhenNotEnabled()
    {
        $this->markTestSkipped('Uses internet connection, find another way to test this.');
        $this->curlHook = new Curl();

        $curlHandle = curl_init('http://example.com/');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        curl_close($curlHandle);
    }

    public function testShouldNotInterceptCallWhenDisabled()
    {
        $this->markTestSkipped('Uses internet connection, find another way to test this.');

        $testClass = $this;
        $this->curlHook->enable(
            function () use ($testClass) {
                $testClass->fail('This request should not have been intercepted.');
            }
        );
        $this->curlHook->disable();

        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, 'http://example.com/');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        curl_close($curlHandle);
    }

    public function testShouldWriteFileOnFileDownload()
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('https://example.com/');
        $filePointer = fopen('php://temp/test_file', 'w');
        curl_setopt($curlHandle, CURLOPT_FILE, $filePointer);
        curl_exec($curlHandle);
        curl_close($curlHandle);
        rewind($filePointer);
        $actual = fread($filePointer, 1024);
        fclose($filePointer);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written in file.');
    }

    public function testShouldEchoResponseIfReturnTransferFalse()
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com/');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, false);
        ob_start();
        curl_exec($curlHandle);
        $actual = ob_get_contents();
        ob_end_clean();
        curl_close($curlHandle);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written on stdout.');
    }

    public function testShouldPostFieldsAsString()
    {
        $testClass = $this;
        $this->curlHook->enable(
            function ($request) use ($testClass) {
                $testClass->assertEquals(
                    array('para1' => 'val1', 'para2' => 'val2'),
                    $request->getPostFields()->getAll(),
                    'Post query string was not parsed and set correctly.'
                );
                return new Response(200);
            }
        );

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, 'para1=val1&para2=val2');
        curl_exec($curlHandle);
        curl_close($curlHandle);
        $this->curlHook->disable();
    }

    public function testShouldPostFieldsAsArray()
    {
        $testClass = $this;
        $this->curlHook->enable(
            function ($request) use ($testClass) {
                $testClass->assertEquals(
                    array('para1' => 'val1', 'para2' => 'val2'),
                    $request->getPostFields()->getAll(),
                    'Post query string was not parsed and set correctly.'
                );
                return new Response(200);
            }
        );

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, array('para1' => 'val1', 'para2' => 'val2'));
        curl_exec($curlHandle);
        curl_close($curlHandle);
        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoStatusCode()
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $infoHttpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        $this->assertEquals(200, $infoHttpCode, 'HTTP status not set.');
        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoAll()
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $info = curl_getinfo($curlHandle);
        curl_close($curlHandle);

        $this->assertTrue(is_array($info), 'curl_getinfo() should return an array.');
        $this->assertEquals(19, count($info), 'curl_getinfo() should return 19 values.');
        $this->curlHook->disable();
    }

    public function testShouldNotThrowErrorWhenDisabledTwice()
    {
        $this->curlHook->disable();
        $this->curlHook->disable();
    }

    public function testShouldNotThrowErrorWhenEnabledTwice()
    {
        $this->curlHook->enable($this->getTestCallback());
        $this->curlHook->enable($this->getTestCallback());
        $this->curlHook->disable();
    }

    /**
     * @return \callable
     */
    protected function getTestCallback()
    {
        $testClass = $this;
        return function () use ($testClass) {
            return new Response(200, null, $testClass->expected);
        };
    }
}