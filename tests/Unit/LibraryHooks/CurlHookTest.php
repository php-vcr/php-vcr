<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\LibraryHooks;

use Assert\Assertion;
use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\CurlCodeTransform;
use VCR\Configuration;
use VCR\LibraryHooks\CurlHook;
use VCR\Request;
use VCR\Response;
use VCR\Util\StreamProcessor;

final class CurlHookTest extends TestCase
{
    /** @var string */
    public $expected = 'example response body';

    protected Configuration $config;

    protected CurlHook $curlHook;

    protected function setup(): void
    {
        $this->config = new Configuration();
        $this->curlHook = new CurlHook(new CurlCodeTransform(), new StreamProcessor($this->config));
    }

    public function testShouldBeEnabledAfterEnabling(): void
    {
        $this->assertFalse($this->curlHook->isEnabled(), 'Initially the CurlHook should be disabled.');

        $this->curlHook->enable($this->getTestCallback());
        $this->assertTrue($this->curlHook->isEnabled(), 'After enabling the CurlHook should be disabled.');

        $this->curlHook->disable();
        $this->assertFalse($this->curlHook->isEnabled(), 'After disabling the CurlHook should be disabled.');
    }

    public function testShouldInterceptCallWhenEnabled(): void
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com/');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        $actual = curl_exec($curlHandle);
        curl_close($curlHandle);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not returned.');
    }

    /**
     * @group uses_internet
     */
    public function testShouldNotInterceptCallWhenNotEnabled(): void
    {
        $curlHandle = curl_init('http://example.com/');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlHandle);
        Assertion::string($response);
        curl_close($curlHandle);

        $this->assertStringContainsString('Example Domain', $response, 'Response from http://example.com should contain "Example Domain".');
    }

    /**
     * @group uses_internet
     */
    public function testShouldNotInterceptCallWhenDisabled(): void
    {
        $intercepted = false;
        $this->curlHook->enable(
            function () use (&$intercepted): void {
                $intercepted = true;
            }
        );
        $this->curlHook->disable();

        $curlHandle = curl_init();
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_URL, 'http://example.com/');
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        curl_close($curlHandle);
        $this->assertFalse($intercepted, 'This request should not have been intercepted.');
    }

    public function testShouldWriteFileOnFileDownload(): void
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('https://example.com/');
        Assertion::notSame($curlHandle, false);
        $filePointer = fopen('php://temp/test_file', 'w');
        Assertion::isResource($filePointer);
        curl_setopt($curlHandle, \CURLOPT_FILE, $filePointer);
        curl_exec($curlHandle);
        curl_close($curlHandle);
        rewind($filePointer);
        $actual = fread($filePointer, 1024);
        fclose($filePointer);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written in file.');
    }

    public function testShouldEchoResponseIfReturnTransferFalse(): void
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com/');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, false);
        ob_start();
        curl_exec($curlHandle);
        $actual = ob_get_contents();
        ob_end_clean();
        curl_close($curlHandle);

        $this->curlHook->disable();
        $this->assertEquals($this->expected, $actual, 'Response was not written on stdout.');
    }

    public function testShouldPostFieldsAsArray(): void
    {
        $testClass = $this;
        $this->curlHook->enable(
            function (Request $request) use ($testClass) {
                $testClass->assertEquals(
                    ['para1' => 'val1', 'para2' => 'val2'],
                    $request->getPostFields(),
                    'Post query string was not parsed and set correctly.'
                );

                return new Response('200');
            }
        );

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, ['para1' => 'val1', 'para2' => 'val2']);
        curl_exec($curlHandle);
        curl_close($curlHandle);
        $this->curlHook->disable();
    }

    public function testShouldPostFieldsAsArrayUsingSetoptarray(): void
    {
        $testClass = $this;
        $this->curlHook->enable(
            function (Request $request) use ($testClass) {
                $testClass->assertEquals(
                    ['para1' => 'val1', 'para2' => 'val2'],
                    $request->getPostFields(),
                    'Post query string was not parsed and set correctly.'
                );

                return new Response('200');
            }
        );

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);
        curl_setopt_array(
            $curlHandle,
            [
                \CURLOPT_POSTFIELDS => ['para1' => 'val1', 'para2' => 'val2'],
            ]
        );
        curl_exec($curlHandle);
        curl_close($curlHandle);
        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoStatusCode(): void
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $infoHttpCode = curl_getinfo($curlHandle, \CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        $this->assertSame(200, $infoHttpCode, 'HTTP status not set.');

        $this->curlHook->disable();
    }

    /**
     * @see https://github.com/php-vcr/php-vcr/issues/136
     */
    public function testShouldReturnCurlInfoStatusCodeAsInteger(): void
    {
        $stringStatusCode = '200';
        $integerStatusCode = 200;
        $this->curlHook->enable($this->getTestCallback($stringStatusCode));

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $infoHttpCode = curl_getinfo($curlHandle, \CURLINFO_HTTP_CODE);
        curl_close($curlHandle);

        $this->assertSame($integerStatusCode, $infoHttpCode, 'HTTP status not set.');

        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoAll(): void
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $info = curl_getinfo($curlHandle);
        curl_close($curlHandle);

        $this->assertIsArray($info, 'curl_getinfo() should return an array.');
        $this->assertCount(22, $info, 'curl_getinfo() should return 22 values.');
        $this->curlHook->disable();
    }

    public function testShouldReturnCurlInfoAllKeys(): void
    {
        $this->curlHook->enable($this->getTestCallback());

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        curl_exec($curlHandle);
        $info = curl_getinfo($curlHandle);
        curl_close($curlHandle);

        $this->assertIsArray($info, 'curl_getinfo() should return an array.');
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

    /**
     * @doesNotPerformAssertions
     */
    public function testShouldNotThrowErrorWhenDisabledTwice(): void
    {
        $this->curlHook->disable();
        $this->curlHook->disable();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testShouldNotThrowErrorWhenEnabledTwice(): void
    {
        $this->curlHook->enable($this->getTestCallback());
        $this->curlHook->enable($this->getTestCallback());
        $this->curlHook->disable();
    }

    public function testShouldInterceptMultiCallWhenEnabled(): void
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

                return new Response('200');
            }
        );

        $curlHandle1 = curl_init('http://example.com');
        $curlHandle2 = curl_init('http://example.com');

        Assertion::notSame($curlHandle1, false);
        Assertion::notSame($curlHandle2, false);

        $curlMultiHandle = curl_multi_init();
        Assertion::notSame($curlMultiHandle, false);
        curl_multi_add_handle($curlMultiHandle, $curlHandle1);
        curl_multi_add_handle($curlMultiHandle, $curlHandle2);

        $stillRunning = null;
        curl_multi_exec($curlMultiHandle, $stillRunning);

        $lastInfo = curl_multi_info_read($curlMultiHandle);
        $secondLastInfo = curl_multi_info_read($curlMultiHandle);
        $afterLastInfo = curl_multi_info_read($curlMultiHandle);

        curl_multi_remove_handle($curlMultiHandle, $curlHandle1);
        curl_multi_remove_handle($curlMultiHandle, $curlHandle2);
        curl_multi_close($curlMultiHandle);

        $this->curlHook->disable();

        $this->assertEquals(2, $callCount, 'Hook should have been called twice.');
        $this->assertEquals(
            ['msg' => 1, 'result' => 0, 'handle' => $curlHandle2],
            $lastInfo,
            'When called the first time curl_multi_info_read should return last curl info.'
        );

        $this->assertEquals(
            ['msg' => 1, 'result' => 0, 'handle' => $curlHandle1],
            $secondLastInfo,
            'When called the second time curl_multi_info_read should return second to last curl info.'
        );

        $this->assertFalse($afterLastInfo, 'Multi info called the last time should return false.');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testShouldNotInterceptMultiCallWhenDisabled(): void
    {
        $testClass = $this;
        $this->curlHook->enable(
            function () use ($testClass): void {
                $testClass->fail('This request should not have been intercepted.');
            }
        );
        $this->curlHook->disable();

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);

        $stillRunning = null;
        $curlMultiHandle = curl_multi_init();
        Assertion::notSame($curlMultiHandle, false);
        curl_multi_add_handle($curlMultiHandle, $curlHandle);
        curl_multi_exec($curlMultiHandle, $stillRunning);
        curl_multi_remove_handle($curlMultiHandle, $curlHandle);
        curl_multi_close($curlMultiHandle);
    }

    public function testShouldReturnMultiCallValues(): void
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

                return new Response('200', [], $testClass->expected.$callCount);
            }
        );

        $curlHandle1 = curl_init('http://example.com');
        Assertion::notSame($curlHandle1, false);
        curl_setopt($curlHandle1, \CURLOPT_RETURNTRANSFER, true);
        $curlHandle2 = curl_init('http://example.com');
        Assertion::notSame($curlHandle2, false);
        curl_setopt($curlHandle2, \CURLOPT_RETURNTRANSFER, true);
        $curlHandle3 = curl_init('http://example.com');
        Assertion::notSame($curlHandle3, false);
        curl_setopt($curlHandle3, \CURLOPT_RETURNTRANSFER, false);

        $curlMultiHandle = curl_multi_init();
        Assertion::notSame($curlMultiHandle, false);
        curl_multi_add_handle($curlMultiHandle, $curlHandle1);
        curl_multi_add_handle($curlMultiHandle, $curlHandle2);
        curl_multi_add_handle($curlMultiHandle, $curlHandle3);

        $stillRunning = null;
        ob_start();
        curl_multi_exec($curlMultiHandle, $stillRunning);
        $output = ob_get_contents();
        ob_end_clean();

        $returnValue1 = curl_multi_getcontent($curlHandle1);
        $returnValue2 = curl_multi_getcontent($curlHandle2);
        $returnValue3 = curl_multi_getcontent($curlHandle3);

        curl_multi_remove_handle($curlMultiHandle, $curlHandle1);
        curl_multi_remove_handle($curlMultiHandle, $curlHandle2);
        curl_multi_remove_handle($curlMultiHandle, $curlHandle3);
        curl_multi_close($curlMultiHandle);

        $this->curlHook->disable();

        $this->assertEquals(3, $callCount, 'Hook should have been called thrice.');
        $this->assertSame(
            $this->expected.'1',
            $returnValue1,
            'When called with the first handle the curl_multi_getcontent should return the body of the first response.'
        );

        $this->assertSame(
            $this->expected.'2',
            $returnValue2,
            'When called with the second handle the curl_multi_getcontent should return the body of the second response.'
        );

        $this->assertNull($returnValue3, 'When called with the third handle the curl_multi_getcontent should return null.');

        $this->assertSame(
            $this->expected.'3',
            $output,
            'The third response was not written on stdout.'
        );
    }

    /**
     * @requires PHP 5.5.0
     */
    public function testShouldResetRequest(): void
    {
        $testClass = $this;
        $this->curlHook->enable(
            function (Request $request) use ($testClass) {
                $testClass->assertEquals(
                    'GET',
                    $request->getMethod(),
                    ''
                );

                return new Response('200');
            }
        );

        $curlHandle = curl_init('http://example.com');
        Assertion::notSame($curlHandle, false);
        curl_setopt($curlHandle, \CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_reset($curlHandle);
        curl_exec($curlHandle);

        $this->curlHook->disable();
    }

    protected function getTestCallback(string $statusCode = '200'): \Closure
    {
        $testClass = $this;

        return \Closure::fromCallable(fn () => new Response($statusCode, [], $testClass->expected));
    }
}
