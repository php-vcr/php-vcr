<?php

namespace VCR\Event;

use VCR\Request;
use VCR\Response;

class AfterHttpRequestEventTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AfterHttpRequestEvent
     */
    private $event;

    protected function setUp()
    {
        $this->event = new AfterHttpRequestEvent(new Request('GET', 'http://example.com'), new Response(200));
    }

    public function testGetRequest()
    {
        $this->assertInstanceOf('VCR\Request', $this->event->getRequest());
    }

    public function testGetResponse()
    {
        $this->assertInstanceOf('VCR\Response', $this->event->getResponse());
    }
}
