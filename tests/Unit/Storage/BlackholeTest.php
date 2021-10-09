<?php

namespace VCR\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Blackhole;

class BlackholeTest extends TestCase
{
    /** @var Blackhole */
    protected $storage;

    protected function setUp(): void
    {
        $this->storage = new Blackhole();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testStoreRecordingIsCallable(): void
    {
        $this->storage->storeRecording([
            'request' => [
                'some' => 'request',
            ],
            'response' => [
                'some' => 'response',
            ],
        ]);
    }

    public function testNextIsCallable(): void
    {
        $this->assertNull($this->storage->next());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testRewindIsCallable(): void
    {
        $this->storage->rewind();
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
