<?php

namespace VCR\LibraryHooks;

use \VCR\Response;

/**
 * Test if intercepting http/https using curl rewrite works.
 */
class CurlRewriteTest extends \PHPUnit_Framework_TestCase
{
    public $expected = 'example response body';

    /**
     * @group runkit
     */
    public function testShouldInterceptCallWhenEnabled()
    {
        $curlHook = new CurlRewrite();
        $curlHook->enable($this->getTestCallback());

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $actual = curl_exec($ch);
        curl_close($ch);

        $curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not returned.');
    }

    /**
     * @group runkit
     */
    public function testShouldNotInterceptCallWhenNotEnabled()
    {
        $this->markTestSkipped('Uses internet connection, find another way to test this.');
        $testClass = $this;
        $curlHook = new CurlRewrite();

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * @group runkit
     */
    public function testShouldNotInterceptCallWhenDisabled()
    {
        $testClass = $this;
        $curlHook = new CurlRewrite();
        $curlHook->enable(function($request) use($testClass) {
            $testClass->fail('This request should not have been intercepted.');
        });
        $curlHook->disable();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * @group runkit
     */
    public function testShouldWriteFileOnFileDownload()
    {
        $curlHook = new CurlRewrite();
        $curlHook->enable($this->getTestCallback());

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

    /**
     * @group runkit
     */
    public function testShouldEchoResponseIfReturnTransferFalse()
    {
        $curlHook = new CurlRewrite();
        $curlHook->enable($this->getTestCallback());

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

    /**
     * @group runkit
     */
    public function testShouldPostFieldsAsString()
    {
        $testClass = $this;
        $curlHook = new CurlRewrite();
        $curlHook->enable(function($request) use($testClass) {
            $testClass->assertEquals(
                array('para1' => 'val1', 'para2' => 'val2'),
                $request->getPostFields()->getAll(),
                'Post query string was not parsed and set correctly.'
            );
            return new Response(200);
        });

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'para1=val1&para2=val2');
        curl_exec($ch);
        curl_close($ch);
        $curlHook->disable();
    }

    /**
     * @group runkit
     */
    public function testShouldPostFieldsAsArray()
    {
        $testClass = $this;
        $curlHook = new CurlRewrite;
        $curlHook->enable(function($request) use($testClass) {
            $testClass->assertEquals(
                array('para1' => 'val1', 'para2' => 'val2'),
                $request->getPostFields()->getAll(),
                'Post query string was not parsed and set correctly.'
            );
            return new Response(200);
        });

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('para1' => 'val1', 'para2' => 'val2'));
        curl_exec($ch);
        curl_close($ch);
        $curlHook->disable();
    }

    /**
     * @group runkit
     */
    public function testShouldReturnCurlInfoStatusCode()
    {
        $curlHook = new CurlRewrite();
        $curlHook->enable($this->getTestCallback());

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        $this->assertEquals(200, curl_getinfo($ch, CURLINFO_HTTP_CODE), 'HTTP status not set.');
        $curlHook->disable();
    }

    /**
     * @group runkit
     */
    public function testShouldReturnCurlInfoAll()
    {
        $curlHook = new CurlRewrite();
        $curlHook->enable($this->getTestCallback());

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        $this->assertTrue(is_array(curl_getinfo($ch)), 'curl_getinfo() should return an array.');
        $this->assertEquals(19, count(curl_getinfo($ch)), 'curl_getinfo() should return 19 values.');
        $curlHook->disable();
    }

    /**
     * @group runkit
     */
    public function testShouldNotThrowErrorWhenDisabledTwice()
    {
        $curlHook = new CurlRewrite();
        $curlHook->disable();
        $curlHook->disable();
    }

    /**
     * @group runkit
     */
    public function testShouldNotThrowErrorWhenEnabledTwice()
    {
        $curlHook = new CurlRewrite();
        $curlHook->enable($this->getTestCallback());
        $curlHook->enable($this->getTestCallback());
    }

    /**
     * @return \callable
     */
    protected function getTestCallback($handleRequestCallback = null)
    {
        $testClass = $this;
        return function($request) use($testClass) {
            return new Response(200, null, $testClass->expected);
        };
    }
}
