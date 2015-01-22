<?php

namespace VCR\Event;

use VCR\Request;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Storage;
use VCR\Response;

class BeforeRecordEventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var BeforeRecordEvent
     */
    private $event;

    protected function setUp()
    {
        $this->event = new BeforeRecordEvent(
            new Request('GET', 'http://example.com'),
            new Response(200),
            new Cassette('test', new Configuration(), new Storage\Blackhole())
        );
    }

    public function testGetRequest()
    {
        $this->assertInstanceOf('VCR\Request', $this->event->getRequest());
    }

    public function testGetResponse()
    {
        $this->assertInstanceOf('VCR\Response', $this->event->getResponse());
    }

    public function testGetCassette()
    {
        $this->assertInstanceOf('VCR\Cassette', $this->event->getCassette());
    }
}
