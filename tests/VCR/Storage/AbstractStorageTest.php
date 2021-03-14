<?php

namespace VCR\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class AbstractStorageTest extends TestCase
{
    /** @var TestStorage */
    protected $storage;

    public function testFilePathCreated(): void
    {
        $fs = vfsStream::setup('test');

        $this->storage = new TestStorage(vfsStream::url('test/'), 'file');
        $this->assertTrue($fs->hasChild('file'));

        $this->storage = new TestStorage(vfsStream::url('test/'), 'folder/file');
        $this->assertTrue($fs->hasChild('folder'));
        $this->assertTrue($fs->getChild('folder')->hasChild('file'));
    }

    public function testRootNotExisting(): void
    {
        $this->expectException(\VCR\VCRException::class);
        $this->expectExceptionMessage("Cassette path 'vfs://test/foo' is not existing or not a directory");

        vfsStream::setup('test');
        new TestStorage(vfsStream::url('test/foo'), 'file');
    }
}

class TestStorage extends AbstractStorage
{
    /** @var array<mixed> */
    private $recording;

    public function storeRecording(array $recording): void
    {
        $this->recording = $recording;
    }

    public function next(): void
    {
        [$this->position, $this->current] = each($this->recording);
    }

    public function valid()
    {
        return (bool) $this->position;
    }

    public function rewind(): void
    {
        reset($this->recording);
    }
}
