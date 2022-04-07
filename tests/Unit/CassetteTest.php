<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Request;
use VCR\Response;
use VCR\Storage\Yaml;

final class CassetteTest extends TestCase
{
    private Cassette $cassette;

    protected function setUp(): void
    {
        vfsStream::setup('test');
        $this->cassette = new Cassette('test', new Configuration(), new Yaml(vfsStream::url('test/'), 'json_test'));
    }

    public function testGetName(): void
    {
        $this->assertEquals('test', $this->cassette->getName());
    }

    public function testDontOverwriteRecord(): void
    {
        $request = new Request('GET', 'https://example.com');
        $response1 = new Response('200', [], 'sometest');
        $response2 = new Response('200', [], 'sometest');
        $this->cassette->record($request, $response1);
        $this->cassette->record($request, $response2);

        $this->assertEquals($response1->toArray(), $this->cassette->playback($request)?->toArray());
    }

    public function testPlaybackAlreadyRecordedRequest(): void
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response('200', [], 'sometest');
        $this->cassette->record($request, $response);

        $this->assertEquals($response->toArray(), $this->cassette->playback($request)?->toArray());
    }

    public function testHasResponseNotFound(): void
    {
        $request = new Request('GET', 'https://example.com');

        $this->assertFalse($this->cassette->hasResponse($request), 'Expected false if request not found.');
    }

    public function testHasResponseFound(): void
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response('200', [], 'sometest');
        $this->cassette->record($request, $response);

        $this->assertTrue($this->cassette->hasResponse($request), 'Expected true if request was found.');
    }
}
