<?php

namespace VCR\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Request;
use VCR\Response;
use VCR\Storage\Yaml;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class CassetteTest extends TestCase
{
    /**
     * @var Cassette
     */
    private $cassette;

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

        $this->assertEquals($response1->toArray(), $this->cassette->playback($request)->toArray());
    }

    public function testPlaybackAlreadyRecordedRequest(): void
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response('200', [], 'sometest');
        $this->cassette->record($request, $response);

        $this->assertEquals($response->toArray(), $this->cassette->playback($request)->toArray());
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

    /**
     * Test playback of a legacy cassette which does not have an index key.
     */
    public function testPlaybackLegacyCassette(): void
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, [], 'sometest');

        // Create recording array with no index key.
        $recording = [
            'request' => $request->toArray(),
            'response' => $response->toArray(),
        ];

        $cassette = $this->createCassetteWithRecordings([$recording]);

        $this->assertEquals($response->toArray(), $cassette->playback($request)->toArray());
    }

    /**
     * Ensure that if a second identical request is played back from a legacy
     * cassette, the first response will be returned.
     */
    public function testPlaybackOfIdenticalRequestsFromLegacyCassette(): void
    {
        $request1 = new Request('GET', 'https://example.com');
        $response1 = new Response(200, [], 'response1');

        $request2 = new Request('GET', 'https://example.com');
        $response2 = new Response(200, [], 'response2');

        // These are legacy recordings with no index keys.
        $recordings = [
            [
                'request' => $request1->toArray(),
                'response' => $response1->toArray(),
            ],
            [
                'request' => $request2->toArray(),
                'response' => $response2->toArray(),
            ],
        ];

        $cassette = $this->createCassetteWithRecordings($recordings);

        $this->assertEquals($response1->toArray(), $cassette->playback($request1)->toArray());
        $this->assertEquals($response1->toArray(), $cassette->playback($request2)->toArray());
    }

    /**
     * Ensure that if a second identical request is played back from an cassette
     * with indexed recordings, the response corresponding to the recording
     * index will be returned.
     */
    public function testPlaybackOfIdenticalRequests(): void
    {
        $request1 = new Request('GET', 'https://example.com');
        $response1 = new Response(200, [], 'response1');

        $request2 = new Request('GET', 'https://example.com');
        $response2 = new Response(200, [], 'response2');

        // These are recordings with index keys which support playback of
        // multiple identical requests.
        $recordings = [
            [
                'request' => $request1->toArray(),
                'response' => $response1->toArray(),
                'index' => 0,
            ],
            [
                'request' => $request2->toArray(),
                'response' => $response2->toArray(),
                'index' => 1,
            ],
        ];

        $cassette = $this->createCassetteWithRecordings($recordings);

        $this->assertEquals($response1->toArray(), $cassette->playback($request1, 0)->toArray());
        $this->assertNotEquals($response1->toArray(), $cassette->playback($request2, 1)->toArray());
        $this->assertEquals($response2->toArray(), $cassette->playback($request2, 1)->toArray());
    }

    protected function createCassetteWithRecordings(array $recordings)
    {
        $storage = new Storage\Yaml(vfsStream::url('test/'), 'json_test');

        foreach ($recordings as $recording) {
            $storage->storeRecording($recording);
        }
        $configuration = new Configuration();

        return new Cassette('cassette_name', $configuration, $storage);
    }
}
