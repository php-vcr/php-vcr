<?php

namespace VCR\Event;

use PHPUnit\Framework\TestCase;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Request;
use VCR\Response;
use VCR\Storage;

class BeforeRecordEventTest extends TestCase
{
    /**
     * @var BeforeRecordEvent
     */
    private $event;

    protected function setUp(): void
    {
        $this->event = new BeforeRecordEvent(
            new Request('GET', 'http://example.com'),
            new Response('200'),
            new Cassette('test', new Configuration(), new Storage\Blackhole())
        );
    }

    public function testGetRequest(): void
    {
        $this->assertInstanceOf('VCR\Request', $this->event->getRequest());
    }

    public function testGetResponse(): void
    {
        $this->assertInstanceOf('VCR\Response', $this->event->getResponse());
    }

    public function testGetCassette(): void
    {
        $this->assertInstanceOf('VCR\Cassette', $this->event->getCassette());
    }
}
