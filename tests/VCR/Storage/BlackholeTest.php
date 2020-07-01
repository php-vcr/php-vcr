<?php

namespace VCR\Storage;

use PHPUnit\Framework\TestCase;

class BlackholeTest extends TestCase
{
    protected $storage;

    public function setUp()
    {
        $this->storage = new Blackhole();
    }

    public function testStoreRecordingIsCallable()
    {
        $this->assertNull($this->storage->storeRecording(['empty or not, we don\'t care']));
    }

    public function testNextIsCallable()
    {
        $this->assertNull($this->storage->next());
    }

    public function testRewindIsCallable()
    {
        $this->assertNull($this->storage->rewind());
    }

    public function testKeyIsNotCallable()
    {
        $this->expectException(\BadMethodCallException::class);

        $this->storage->key();
    }

    public function testCurrentIsNotCallable()
    {
        $this->expectException(\BadMethodCallException::class);

        $this->storage->current();
    }

    public function testValidIsAlwaysFalse()
    {
        $this->assertFalse($this->storage->valid());
    }

    public function testIsNewIsAlwaysTrue()
    {
        $this->assertTrue($this->storage->isNew());
    }
}
