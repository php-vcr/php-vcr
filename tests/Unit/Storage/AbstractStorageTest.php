<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use VCR\Storage\AbstractStorage;

final class AbstractStorageTest extends TestCase
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
        $this->assertInstanceOf(vfsStreamDirectory::class, $child = $fs->getChild('folder'));
        $this->assertTrue($child->hasChild('file'));
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
    }

    public function valid(): bool
    {
        return (bool) $this->position;
    }

    public function rewind(): void
    {
        reset($this->recording);
    }
}
