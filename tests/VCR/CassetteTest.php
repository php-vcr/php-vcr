<?php

namespace VCR;

use org\bovigo\vfs\vfsStream;


/**
 * Test integration of PHPVCR with PHPUnit.
 */
class CassetteTest extends \PHPUnit_Framework_TestCase
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

    public function testInvalidCassetteName()
    {
        $this->setExpectedException('\VCR\VCRException', 'Cassette name must be a string, array given.');
        new Cassette(array(), new Configuration(), new Storage\Yaml(vfsStream::url('test/'), 'json_test'));
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
        $this->cassette->record($request, $response1);
        $this->cassette->record($request, $response2);

        $this->assertEquals($response1->toArray(), $this->cassette->playback($request)->toArray());
    }

    public function testPlaybackAlreadyRecordedRequest()
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, array(), 'sometest');
        $this->cassette->record($request, $response);

        $this->assertEquals($response->toArray(), $this->cassette->playback($request)->toArray());
    }

    public function testHasResponseNotFound()
    {
        $request = new Request('GET', 'https://example.com');

        $this->assertFalse($this->cassette->hasResponse($request), 'Expected false if request not found.');
    }

    public function testHasResponseFound()
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, array(), 'sometest');
        $this->cassette->record($request, $response);

        $this->assertTrue($this->cassette->hasResponse($request), 'Expected true if request was found.');
    }

    /**
     * Ensure that if a second identical request is played back from a legacy
     * cassette, the first response will be returned.
     */
    public function testPlaybackOfIdenticalRequestsFromLegacyCassette() {

        $request1 = new Request('GET', 'https://example.com');
        $response1 = new Response(200, array(), 'response1');

        $request2 = new Request('GET', 'https://example.com');
        $response2 = new Response(200, array(), 'response2');

        // These are legacy recordings with no index keys.
        $recordings = array(
            array(
                'request' => $request1->toArray(),
                'response' => $response1->toArray()
            ),
            array(
                'request' => $request2->toArray(),
                'response' => $response2->toArray()
            ),
        );

        $cassette = $this->createCassetteWithRecordings($recordings);

        $this->assertEquals($response1->toArray(), $cassette->playback($request1)->toArray());
        $this->assertEquals($response1->toArray(), $cassette->playback($request2)->toArray());
    }

    /**
     * Ensure that if a second identical request is played back from an cassette
     * with indexed recordings, the response corresponding to the recording
     * index will be returned.
     */
    public function testPlaybackOfIdenticalRequests() {
        $request1 = new Request('GET', 'https://example.com');
        $response1 = new Response(200, array(), 'response1');

        $request2 = new Request('GET', 'https://example.com');
        $response2 = new Response(200, array(), 'response2');

        // These are recordings with index keys which support playback of
        // multiple identical requests.
        $recordings = array(
            array(
                'request' => $request1->toArray(),
                'response' => $response1->toArray(),
                'index' => 0
            ),
            array(
                'request' => $request2->toArray(),
                'response' => $response2->toArray(),
                'index' => 1
            ),
        );

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
