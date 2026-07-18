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

    public function testFollowsRedirectByDefault(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $calls = 0;
        $hook->enable(static function ($request) use (&$calls) {
            ++$calls;
            if (1 === $calls) {
                return new Response('301', ['Location' => 'http://example.com/final'], 'moved');
            }

            return new Response('200', [], 'final body');
        });
        $hook->stream_open('http://example.com/start', 'r', 0, $openedPath);

        $this->assertSame(2, $calls);
        $this->assertSame('final body', $hook->stream_read(100));
        $hook->disable();
    }

    public function testDoesNotFollowWhenContextDisablesFollowLocation(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $calls = 0;
        $hook->enable(static function ($request) use (&$calls) {
            ++$calls;

            return new Response('301', ['Location' => 'http://example.com/final'], 'moved');
        });
        $hook->context = stream_context_create(['http' => ['follow_location' => 0]]);
        $hook->stream_open('http://example.com/start', 'r', 0, $openedPath);

        $this->assertSame(1, $calls);
        $this->assertSame('moved', $hook->stream_read(100));
        $hook->disable();
    }

    public function testFollowsWhenContextExplicitlyEnablesFollowLocation(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $calls = 0;
        $hook->enable(static function ($request) use (&$calls) {
            ++$calls;
            if (1 === $calls) {
                return new Response('301', ['Location' => 'http://example.com/final'], 'moved');
            }

            return new Response('200', [], 'final body');
        });
        $hook->context = stream_context_create(['http' => ['follow_location' => 1]]);
        $hook->stream_open('http://example.com/start', 'r', 0, $openedPath);

        $this->assertSame(2, $calls);
        $this->assertSame('final body', $hook->stream_read(100));
        $hook->disable();
    }

    public function testDoesNotFollowNonRedirectResponseWithLocationHeader(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $calls = 0;
        $hook->enable(static function ($request) use (&$calls) {
            ++$calls;

            // 200 with a stray Location header must not trigger a follow.
            return new Response('200', ['Location' => 'http://example.com/final'], 'body');
        });
        $hook->stream_open('http://example.com/start', 'r', 0, $openedPath);

        $this->assertSame(1, $calls);
        $this->assertSame('body', $hook->stream_read(100));
        $hook->disable();
    }

    public function testFallsBackToRedirectResponseWhenHopUnavailable(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $calls = 0;
        $hook->enable(static function ($request) use (&$calls) {
            ++$calls;
            if (1 === $calls) {
                return new Response('301', ['Location' => 'http://example.com/final'], 'moved');
            }

            throw new \LogicException('no recorded response for the redirect target');
        });
        $hook->stream_open('http://example.com/start', 'r', 0, $openedPath);

        $this->assertSame(2, $calls);
        $this->assertSame('moved', $hook->stream_read(100));
        $hook->disable();
    }

    public function testFollowsRelativeRedirect(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $seen = [];
        $hook->enable(static function ($request) use (&$seen) {
            $seen[] = $request->getUrl();
            if (1 === \count($seen)) {
                return new Response('302', ['Location' => '/target'], 'moved');
            }

            return new Response('200', [], 'ok');
        });
        $hook->stream_open('http://example.com/a/b', 'r', 0, $openedPath);

        $this->assertSame(['http://example.com/a/b', 'http://example.com/target'], $seen);
        $this->assertSame('ok', $hook->stream_read(100));
        $hook->disable();
    }

    public function testStopsAfterMaxRedirects(): void
    {
        $hook = new StreamWrapperHook(new StreamWrapperCodeTransform(), new StreamProcessor(new Configuration()));
        $calls = 0;
        $hook->enable(static function ($request) use (&$calls) {
            ++$calls;

            return new Response('301', ['Location' => 'http://example.com/loop'], 'loop');
        });
        $hook->stream_open('http://example.com/loop', 'r', 0, $openedPath);

        // 1 initial request + 20 follows (default max_redirects)
        $this->assertSame(21, $calls);
        $hook->disable();
    }
}
