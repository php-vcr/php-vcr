<?php

namespace VCR\Storage;

class BlackholeTest extends \PHPUnit_Framework_TestCase
{
    protected $storage;

    public function setUp()
    {
        $this->storage = new Blackhole();
    }

    public function testStoreRecordingIsCallable()
    {
        $this->assertNull($this->storage->storeRecording(array('empty or not, we don\'t care')));
    }

    public function testNextIsCallable()
    {
        $this->assertNull($this->storage->next());
    }

    public function testRewindIsCallable()
    {
        $this->assertNull($this->storage->rewind());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testKeyIsNotCallable()
    {
        $this->storage->key();
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testCurrentIsNotCallable()
    {
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
