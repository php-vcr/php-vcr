<?php

namespace VCR;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class CassetteTest extends TestCase
{

    /**
     * @var Cassette
     */
    private $cassette;

    public function setUp()
    {
        vfsStream::setup('test');
        $this->cassette = new Cassette('test', new Configuration(), new Storage\Yaml(vfsStream::url('test/'), 'json_test'));
    }

    public function testGetName()
    {
        $this->assertEquals('test', $this->cassette->getName());
    }

    public function testDontOverwriteRecord()
    {
        $request = new Request('GET', 'https://example.com');
        $response1 = new Response(200, array(), 'sometest');
        $response2 = new Response(200, array(), 'sometest');
        $this->cassette->record($response1);
        $this->cassette->record($response2);

        $this->assertEquals($response1->toArray(), $this->cassette->playback()->toArray());
    }

    public function testPlaybackAlreadyRecordedRequest()
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, array(), 'sometest');
        $this->cassette->record($response);

        $this->assertEquals($response->toArray(), $this->cassette->playback()->toArray());
    }

    public function testHasResponseNotFound()
    {
        $request = new Request('GET', 'https://example.com');

        $this->assertFalse($this->cassette->hasResponse(), 'Expected false if request not found.');
    }

    public function testHasResponseFound()
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, array(), 'sometest');
        $this->cassette->record($response);

        $this->assertTrue($this->cassette->hasResponse(), 'Expected true if request was found.');
    }
}
