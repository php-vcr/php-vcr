<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Event\BeforePlaybackEvent;
use VCR\Request;
use VCR\Storage;

final class BeforePlaybackEventTest extends TestCase
{
    private BeforePlaybackEvent $event;

    protected function setUp(): void
    {
        $this->event = new BeforePlaybackEvent(
            new Request('GET', 'http://example.com'),
            new Cassette('test', new Configuration(), new Storage\Blackhole())
        );
    }

    public function testGetRequest(): void
    {
        $this->assertInstanceOf(Request::class, $this->event->getRequest());
    }

    public function testGetCassette(): void
    {
        $this->assertInstanceOf(Cassette::class, $this->event->getCassette());
    }
}
