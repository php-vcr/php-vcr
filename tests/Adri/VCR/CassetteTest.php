<?php

namespace Adri\VCR;


/**
 * Test integration of PHPVCR with PHPUnit.
 */
class CassetteTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->cassette = new Cassette('test', new Configuration);
    }

    public function testRecordAndPlaybackRequest()
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(200, null, 'sometest');
        $this->cassette->record($request, $response);

        $this->assertEquals($response->toArray(), $this->cassette->playback($request)->toArray());
    }

    public function tearDown()
    {
    }
}
