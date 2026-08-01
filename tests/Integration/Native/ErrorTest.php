<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Native;

use VCR\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Connection-failure behaviour for bare curl_*, curl_multi_* and stream_wrapper calls,
 * verifying VCR::LibraryHooks\CurlHook and StreamWrapperHook simulate the underlying
 * functions' native failure behaviour instead of leaking an internal parsing error.
 */
final class ErrorTest extends AbstractIntegrationTestCase
{
    private const UNBOUND_URL = 'http://localhost:9959';

    public function testCurlConnectError(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-curl-error.yml');

        $ch = curl_init(self::UNBOUND_URL);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        \VCR\VCR::turnOff();

        $this->assertFalse($result, 'curl_exec() must return false on connection failure, like real curl.');
        $this->assertNotSame(0, $errno, 'curl_errno() must report a non-zero error code.');
        $this->assertNotSame('', $error, 'curl_error() must report a non-empty error message.');
    }

    public function testCurlMultiConnectError(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-curl-multi-error.yml');

        $ch = curl_init(self::UNBOUND_URL);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);

        $multiHandle = curl_multi_init();
        curl_multi_add_handle($multiHandle, $ch);

        $stillRunning = null;
        curl_multi_exec($multiHandle, $stillRunning);
        $info = curl_multi_info_read($multiHandle);

        curl_multi_remove_handle($multiHandle, $ch);
        curl_multi_close($multiHandle);
        curl_close($ch);

        \VCR\VCR::turnOff();

        $this->assertIsArray($info, 'curl_multi_info_read() must report the failed transfer.');
        $this->assertNotSame(
            \CURLE_OK,
            $info['result'],
            'curl_multi_info_read() must report a non-zero result for a connection failure.'
        );
    }

    public function testStreamWrapperConnectError(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-stream-wrapper-error.yml');

        $result = @file_get_contents(self::UNBOUND_URL);

        \VCR\VCR::turnOff();

        $this->assertFalse($result, 'file_get_contents() must return false on connection failure, like real streams.');
    }
}
