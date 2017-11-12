<?php

namespace VCR\Event;

use VCR\Request;
use PHPUnit\Framework\TestCase;

class BeforeHttpRequestEventTest extends TestCase
{
    /**
     * @var BeforeHttpRequestEvent
     */
    private $event;

    protected function setUp()
    {
        $this->event = new BeforeHttpRequestEvent(new Request('GET', 'http://example.com'));
    }

    public function testGetRequest()
    {
        $this->assertInstanceOf('VCR\Request', $this->event->getRequest());
    }
}
