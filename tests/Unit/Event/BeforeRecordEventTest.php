<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Event\BeforeRecordEvent;
use VCR\Request;
use VCR\Response;
use VCR\Storage;

final class BeforeRecordEventTest extends TestCase
{
    private BeforeRecordEvent $event;

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
        $this->assertInstanceOf(Request::class, $this->event->getRequest());
    }

    public function testGetResponse(): void
    {
        $this->assertInstanceOf(Response::class, $this->event->getResponse());
    }

    public function testGetCassette(): void
    {
        $this->assertInstanceOf(Cassette::class, $this->event->getCassette());
    }
}
