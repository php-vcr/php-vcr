<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use VCR\Event\BeforeHttpRequestEvent;
use VCR\Request;

final class BeforeHttpRequestEventTest extends TestCase
{
    private BeforeHttpRequestEvent $event;

    protected function setUp(): void
    {
        $this->event = new BeforeHttpRequestEvent(new Request('GET', 'http://example.com'));
    }

    public function testGetRequest(): void
    {
        $this->assertInstanceOf(Request::class, $this->event->getRequest());
    }
}
