<?php

namespace VCR\Event;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\Response;

class AfterHttpRequestEventTest extends TestCase
{
    /**
     * @var AfterHttpRequestEvent
     */
    private $event;

    protected function setUp(): void
    {
        $this->event = new AfterHttpRequestEvent(new Request('GET', 'http://example.com'), new Response('200'));
    }

    public function testGetRequest(): void
    {
        $this->assertInstanceOf('VCR\Request', $this->event->getRequest());
    }

    public function testGetResponse(): void
    {
        $this->assertInstanceOf('VCR\Response', $this->event->getResponse());
    }
}
