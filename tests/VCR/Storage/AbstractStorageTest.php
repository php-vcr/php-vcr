<?php

namespace VCR\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class AbstractStorageTest extends TestCase
{
    protected $handle;
    protected $filePath;
    protected $storage;

    public function testFilePathCreated()
    {
        $fs = vfsStream::setup('test');

        $this->storage = new TestStorage(vfsStream::url('test/'), 'file');
        $this->assertTrue($fs->hasChild('file'));

        $this->storage = new TestStorage(vfsStream::url('test/'), 'folder/file');
        $this->assertTrue($fs->hasChild('folder'));
        $this->assertTrue($fs->getChild('folder')->hasChild('file'));
    }

    /**
     * @expectedException VCR\VCRException
     * @expectedExceptionMessage Cassette path 'vfs://test/foo' is not existing or not a directory
     */
    public function testRootNotExisting()
    {
        vfsStream::setup('test');
        new TestStorage(vfsStream::url('test/foo'), 'file');
    }
}

class TestStorage extends AbstractStorage
{
    private $recording;

    public function storeRecording(array $recording)
    {
        $this->recording = $recording;
    }

    public function next()
    {
        list($this->position, $this->current) = each($this->recording);

        return $this->current;
    }

    public function valid()
    {
        return (boolean) $this->position;
    }

    public function rewind()
    {
        reset($this->recording);
    }
}
