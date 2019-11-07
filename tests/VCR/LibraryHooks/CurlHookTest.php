<?php

namespace VCR\LibraryHooks;

use VCR\Request;
use VCR\Response;
use VCR\Configuration;
use VCR\CodeTransform\CurlCodeTransform;
use VCR\Util\StreamProcessor;

/**
 * Test if intercepting http/https using curl works.
 */
class CurlHookTest extends \PHPUnit_Framework_TestCase
{
    public $expected = 'example response body';
    /**
     * @var \VCR\Configuration
     */
    protected $config;
    /**
     * @var \VCR\LibraryHooks\CurlHook
     */
    protected $curlHook;

    public function setup()
    {
        $this->config = new Configuration();
        $this->curlHook = new CurlHook(new CurlCodeTransform(), new StreamProcessor($this->config));
    }

    public function testShouldBeEnabledAfterEnabling()
    {
        $this->assertFalse($this->curlHook->isEnabled(), 'Initially the CurlHook should be disabled.');

        $this->curlHook->enable($this->getTestCallback());
        $this->assertTrue($this->curlHook->isEnabled(), 'After enabling the CurlHook should be disabled.');

        $this->curlHook->disable();
        $this->assertFalse($this->curlHook->isEnabled(), 'After disabling the CurlHook should be disabled.');
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

    /**
     * @group uses_internet
     */
    public function testShouldNotInterceptCallWhenNotEnabled()
    {
        $curlHandle = curl_init('http://example.com/');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlHandle);
        curl_close($curlHandle);

        $this->assertContains('Example Domain', $response, 'Response from http://example.com should contain "Example Domain".');
    }

    /**
     * @group uses_internet
     */
    public function testShouldNotInterceptCallWhenDisabled()
    {
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

    public function testShouldPostFieldsAsArray()
    {
        $testClass = $this;
        $this->curlHook->enable(
            function (Request $request) use ($testClass) {
                $testClass->assertEquals(
                    array('para1' => 'val1', 'para2' => 'val2'),
                    $request->getPostFields(),
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

    public function testShouldPostFieldsAsArrayUsingSetoptarray()
    {
        $testClass = $this;
        $this->curlHook->enable(
            function (Request $request) use ($testClass) {
                $testClass->assertEquals(
                    array('para1' => 'val1', 'para2' => 'val2'),
                    $request->getPostFields(),
                    'Post query string was not parsed and set correctly.'
                );
                return new Response(200);
            }
        );

        $curlHandle = curl_init('http://example.com');
        curl_setopt_array(
            $curlHandle,
            array(
                CURLOPT_POSTFIELDS => array('para1' => 'val1', 'para2' => 'val2')
            )
        );
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

        $this->assertSame(200, $infoHttpCode, 'HTTP status not set.');

        $this->curlHook->disable();
    }

    /**
     * @see https://github.com/php-vcr/php-vcr/issues/136
     */
    public function testShouldReturnCurlInfoStatusCodeAsInteger()
    {
        $stringStatusCode = '200';
        $integerStatusCode = 200;
        $this->curlHook->enable($this->getTestCallback($stringStatusCode));

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $infoHttpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        $this->assertSame($integerStatusCode, $infoHttpCode, 'HTTP status not set.');

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

        $this->assertInternalType('array', $info, 'curl_getinfo() should return an array.');
        $this->assertCount(21, $info, 'curl_getinfo() should return 21 values.');
        $this->curlHook->disable();
    }

    public function testShouldHandleCurlOptPrivate()
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_PRIVATE, 'private');

        $this->assertEquals('private', curl_getinfo($curlHandle, CURLINFO_PRIVATE));

        curl_exec($curlHandle);
        curl_close($curlHandle);

        $this->assertEquals('private', curl_getinfo($curlHandle, CURLINFO_PRIVATE));
        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoAllKeys()
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $info = curl_getinfo($curlHandle);
        curl_close($curlHandle);

        $this->assertInternalType('array', $info, 'curl_getinfo() should return an array.');
        $this->assertArrayHasKey('url', $info);
        $this->assertArrayHasKey('content_type', $info);
        $this->assertArrayHasKey('http_code', $info);
        $this->assertArrayHasKey('header_size', $info);
        $this->assertArrayHasKey('request_size', $info);
        $this->assertArrayHasKey('filetime', $info);
        $this->assertArrayHasKey('ssl_verify_result', $info);
        $this->assertArrayHasKey('redirect_count', $info);
        $this->assertArrayHasKey('total_time', $info);
        $this->assertArrayHasKey('namelookup_time', $info);
        $this->assertArrayHasKey('connect_time', $info);
        $this->assertArrayHasKey('pretransfer_time', $info);
        $this->assertArrayHasKey('size_upload', $info);
        $this->assertArrayHasKey('size_download', $info);
        $this->assertArrayHasKey('speed_download', $info);
        $this->assertArrayHasKey('speed_upload', $info);
        $this->assertArrayHasKey('download_content_length', $info);
        $this->assertArrayHasKey('upload_content_length', $info);
        $this->assertArrayHasKey('starttransfer_time', $info);
        $this->assertArrayHasKey('redirect_time', $info);
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

    public function testShouldInterceptMultiCallWhenEnabled()
    {
        $testClass = $this;
        $callCount = 0;
        $this->curlHook->enable(
            function (Request $request) use ($testClass, &$callCount) {
                $testClass->assertEquals(
                    'example.com',
                    $request->getHost(),
                    ''
                );
                ++$callCount;
                return new Response(200);
            }
        );

        $curlHandle1 = curl_init('http://example.com');
        $curlHandle2 = curl_init('http://example.com');

        $curlMultiHandle = curl_multi_init();
        curl_multi_add_handle($curlMultiHandle, $curlHandle1);
        curl_multi_add_handle($curlMultiHandle, $curlHandle2);

        $mh = curl_multi_exec($curlMultiHandle);

        $lastInfo       = curl_multi_info_read($mh);
        $secondLastInfo = curl_multi_info_read($mh);
        $afterLastInfo  = curl_multi_info_read($mh);

        curl_multi_remove_handle($curlMultiHandle, $curlHandle1);
        curl_multi_remove_handle($curlMultiHandle, $curlHandle2);
        curl_multi_close($curlMultiHandle);

        $this->curlHook->disable();

        $this->assertEquals(2, $callCount, 'Hook should have been called twice.');
        $this->assertEquals(
            array('msg' => 1, 'result' => 0, 'handle' => $curlHandle2),
            $lastInfo,
            'When called the first time curl_multi_info_read should return last curl info.'
        );

        $this->assertEquals(
            array('msg' => 1, 'result' => 0, 'handle' => $curlHandle1),
            $secondLastInfo,
            'When called the second time curl_multi_info_read should return second to last curl info.'
        );

        $this->assertFalse($afterLastInfo, 'Multi info called the last time should return false.');
    }

    public function testShouldNotInterceptMultiCallWhenDisabled()
    {
        $testClass = $this;
        $this->curlHook->enable(
            function () use ($testClass) {
                $testClass->fail('This request should not have been intercepted.');
            }
        );
        $this->curlHook->disable();

        $curlHandle = curl_init('http://example.com');

        $curlMultiHandle = curl_multi_init();
        curl_multi_add_handle($curlMultiHandle, $curlHandle);
        curl_multi_exec($curlMultiHandle);
        curl_multi_remove_handle($curlMultiHandle, $curlHandle);
        curl_multi_close($curlMultiHandle);
    }

    /**
     * @requires PHP 5.5.0
     */
    public function testShouldResetRequest()
    {
        $testClass = $this;
        $this->curlHook->enable(
            function (Request $request) use ($testClass) {
                $testClass->assertEquals(
                    'GET',
                    $request->getMethod(),
                    ''
                );
                return new Response(200);
            }
        );

        $curlHandle = curl_init('http://example.com');
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_reset($curlHandle);
        curl_exec($curlHandle);

        $this->curlHook->disable();
    }

    /**
     * @return \callable
     */
    protected function getTestCallback($statusCode = 200)
    {
        $testClass = $this;
        return function () use ($statusCode, $testClass) {
            return new Response($statusCode, array(), $testClass->expected);
        };
    }
}
