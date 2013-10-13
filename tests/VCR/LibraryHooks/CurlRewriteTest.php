<?php

namespace VCR\LibraryHooks;

use VCR\Response;
use VCR\Configuration;
use VCR\LibraryHooks\CurlRewrite\Filter;
use VCR\Util\StreamProcessor;


/**
 * Test if intercepting http/https using curl rewrite works.
 */
class CurlRewriteTest extends \PHPUnit_Framework_TestCase
{
    public $expected = 'example response body';

    public function setup()
    {
        $this->config = new Configuration();
        $this->curlHook = new CurlRewrite(new Filter(), new StreamProcessor($this->config));
    }

    public function testShouldInterceptCallWhenEnabled()
    {
        $this->curlHook->enable($this->getTestCallback());

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $actual = curl_exec($ch);
        curl_close($ch);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not returned.');
    }

    public function testShouldNotInterceptCallWhenNotEnabled()
    {
        $this->markTestSkipped('Uses internet connection, find another way to test this.');
        $testClass = $this;
        $this->curlHook = new CurlRewrite();

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function testShouldNotInterceptCallWhenDisabled()
    {
        $testClass = $this;
        $this->curlHook->enable(function($request) use($testClass) {
            $testClass->fail('This request should not have been intercepted.');
        });
        $this->curlHook->disable();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public function testShouldWriteFileOnFileDownload()
    {
        $this->curlHook->enable($this->getTestCallback());

        $ch = curl_init('https://127.0.0.1/');
        $fp = fopen('php://temp/test_file', 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        rewind($fp);
        $actual = fread($fp, 1024);
        fclose($fp);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written in file.');
    }

    public function testShouldEchoResponseIfReturnTransferFalse()
    {
        $this->curlHook->enable($this->getTestCallback());

        $ch = curl_init('http://127.0.0.1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        ob_start();
        curl_exec($ch);
        $actual = ob_get_contents();
        ob_end_clean();
        curl_close($ch);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written on stdout.');
    }

    public function testShouldPostFieldsAsString()
    {
        $testClass = $this;
        $this->curlHook->enable(function($request) use($testClass) {
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
        $this->curlHook->disable();
    }

    public function testShouldPostFieldsAsArray()
    {
        $testClass = $this;
        $this->curlHook->enable(function($request) use($testClass) {
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
        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoStatusCode()
    {
        $this->curlHook->enable($this->getTestCallback());

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $infoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(200, $infoHttpCode, 'HTTP status not set.');
        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoAll()
    {
        $this->curlHook->enable($this->getTestCallback());

        $ch = curl_init('http://127.0.0.1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

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
    protected function getTestCallback($handleRequestCallback = null)
    {
        $testClass = $this;
        return function($request) use($testClass) {
            return new Response(200, null, $testClass->expected);
        };
    }
}
