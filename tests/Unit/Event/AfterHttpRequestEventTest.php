<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use VCR\Event\AfterHttpRequestEvent;
use VCR\Request;
use VCR\Response;

final class AfterHttpRequestEventTest extends TestCase
{
    private AfterHttpRequestEvent $event;

    protected function setUp(): void
    {
        $this->event = new AfterHttpRequestEvent(new Request('GET', 'http://example.com'), new Response('200'));
    }

    public function testGetRequest(): void
    {
        $this->assertInstanceOf(Request::class, $this->event->getRequest());
    }

    public function testGetResponse(): void
    {
        $this->assertInstanceOf(Response::class, $this->event->getResponse());
    }
}
