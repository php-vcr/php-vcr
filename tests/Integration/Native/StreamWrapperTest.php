<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Native;

use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Verifies VCR interception of native file_get_contents / stream_context_create
 * calls via StreamWrapperHook.
 * Cassette names prefixed 'native-sw-' to avoid VCRFactory cache collisions.
 */
final class StreamWrapperTest extends AbstractHttpServerIntegrationTestCase
{
    public function testGetRequestRecordAndReplay(): void
    {
        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-sw-get.yml');
        $b1 = file_get_contents(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertNotFalse($b1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-sw-get.yml');
        $b2 = file_get_contents(self::$baseUrl.'/get');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertNotFalse($b2);
    }

    public function testPassthroughGetRequest(): void
    {
        $countBefore = $this->server()->getRequestCount();
        $body = file_get_contents(self::$baseUrl.'/get');
        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertNotFalse($body);
    }

    public function testPostRequestRecordAndReplay(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => 'hello=world',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            ],
        ]);

        $countBefore = $this->server()->getRequestCount();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-sw-post.yml');
        $b1 = file_get_contents(self::$baseUrl.'/post', false, $context);
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 1, $countAfterRecord, 'Record must hit the server');
        $this->assertNotFalse($b1);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('native-sw-post.yml');
        $b2 = file_get_contents(self::$baseUrl.'/post', false, $context);
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertNotFalse($b2);
    }

    public function testPassthroughPostRequest(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => 'hello=world',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            ],
        ]);

        $countBefore = $this->server()->getRequestCount();
        $body = file_get_contents(self::$baseUrl.'/post', false, $context);
        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'Passthrough must hit the server');
        $this->assertNotFalse($body);
    }
}
