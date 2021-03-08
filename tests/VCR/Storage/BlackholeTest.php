<?php

namespace VCR\Storage;

use PHPUnit\Framework\TestCase;

class BlackholeTest extends TestCase
{
    protected $storage;

    protected function setUp(): void
    {
        $this->storage = new Blackhole();
    }

    public function testStoreRecordingIsCallable(): void
    {
        $this->assertNull($this->storage->storeRecording(['empty or not, we don\'t care']));
    }

    public function testNextIsCallable(): void
    {
        $this->assertNull($this->storage->next());
    }

    public function testRewindIsCallable(): void
    {
        $this->assertNull($this->storage->rewind());
    }

    public function testKeyIsNotCallable(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $this->storage->key();
    }

    public function testCurrentIsNotCallable(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $this->storage->current();
    }

    public function testValidIsAlwaysFalse(): void
    {
        $this->assertFalse($this->storage->valid());
    }

    public function testIsNewIsAlwaysTrue(): void
    {
        $this->assertTrue($this->storage->isNew());
    }
}
