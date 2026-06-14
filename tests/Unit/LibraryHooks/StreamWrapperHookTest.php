<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\LibraryHooks;

use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\StreamWrapperCodeTransform;
use VCR\Configuration;
use VCR\LibraryHooks\StreamWrapperHook;
use VCR\Response;
use VCR\Util\StreamProcessor;

final class StreamWrapperHookTest extends TestCase
{
    public function testEnable(): void
    {
        $streamWrapper = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));

        $testClass = $this;
        $streamWrapper->enable(static function ($request) use ($testClass): void {
            $testClass->assertInstanceOf('\VCR\Request', $request);
        });
        $this->assertTrue($streamWrapper->isEnabled());
        $streamWrapper->disable();
    }

    public function testDisable(): void
    {
        $streamWrapper = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $streamWrapper->disable();
        $this->assertFalse($streamWrapper->isEnabled());
    }

    public function testSeek(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $hook->enable(static fn ($request) => new Response('200', [], 'A Test'));
        $hook->stream_open('http://example.com', 'r', 0, $openedPath);

        $this->assertFalse($hook->stream_seek(-1, \SEEK_SET));
        $this->assertTrue($hook->stream_seek(0, \SEEK_SET));
        $this->assertEquals('A', $hook->stream_read(1));

        $this->assertFalse($hook->stream_seek(-1, \SEEK_CUR));
        $this->assertTrue($hook->stream_seek(1, \SEEK_CUR));
        $this->assertEquals('Test', $hook->stream_read(4));

        $this->assertFalse($hook->stream_seek(-1000, \SEEK_END));
        $this->assertTrue($hook->stream_seek(-4, \SEEK_END));
        $this->assertEquals('Test', $hook->stream_read(4));

        // invalid whence
        $this->assertFalse($hook->stream_seek(0, -1));

        $hook->disable();
    }

    public function testStreamGetMetaDataReplacesWrapperDataWithResponseHeaderLines(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $hook->enable(static fn ($request) => new Response(
            ['code' => '200', 'message' => 'OK', 'http_version' => '1.1'],
            ['Content-Type' => 'text/plain', 'X-Custom' => 'value'],
            'body'
        ));

        $resource = fopen('http://example.com', 'r');
        $this->assertIsResource($resource);

        $meta = StreamWrapperHook::streamGetMetaData($resource);

        $this->assertIsArray($meta['wrapper_data']);
        $this->assertSame('HTTP/1.1 200 OK', $meta['wrapper_data'][0]);
        $this->assertContains('Content-Type: text/plain', $meta['wrapper_data']);
        $this->assertContains('X-Custom: value', $meta['wrapper_data']);

        fclose($resource);
        $hook->disable();
    }

    public function testStreamGetMetaDataPassesThroughForNonVcrStream(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        $expected = stream_get_meta_data($resource);
        $actual = StreamWrapperHook::streamGetMetaData($resource);

        $this->assertEquals($expected, $actual);

        fclose($resource);
    }

    public function testStreamGetMetaDataUsesDefaultHttpVersionWhenNoneSet(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $hook->enable(static fn ($request) => new Response(
            ['code' => '204', 'message' => 'No Content'],
            [],
            ''
        ));

        $resource = fopen('http://example.com', 'r');
        $this->assertIsResource($resource);

        $meta = StreamWrapperHook::streamGetMetaData($resource);

        $this->assertIsArray($meta['wrapper_data']);
        $this->assertSame('HTTP/1.1 204 No Content', $meta['wrapper_data'][0]);

        fclose($resource);
        $hook->disable();
    }
}
