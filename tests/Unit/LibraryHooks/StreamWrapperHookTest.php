<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\LibraryHooks;

use PHPUnit\Framework\TestCase;
use VCR\LibraryHooks\StreamWrapperHook;
use VCR\Response;

final class StreamWrapperHookTest extends TestCase
{
    public function testEnable(): void
    {
        $streamWrapper = new StreamWrapperHook();

        $testClass = $this;
        $streamWrapper->enable(function ($request) use ($testClass): void {
            $testClass->assertInstanceOf('\VCR\Request', $request);
        });
        $this->assertTrue($streamWrapper->isEnabled());
    }

    public function testDisable(): void
    {
        $streamWrapper = new StreamWrapperHook();
        $streamWrapper->disable();
        $this->assertFalse($streamWrapper->isEnabled());
    }

    public function testSeek(): void
    {
        $hook = new StreamWrapperHook();
        $hook->enable(fn ($request) => new Response('200', [], 'A Test'));
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
    }
}
