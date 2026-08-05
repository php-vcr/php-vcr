<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\Json;
use VCR\Storage\JsonStorageFactory;
use VCR\Storage\StorageFactoryInterface;

final class JsonStorageFactoryTest extends TestCase
{
    public function testImplementsStorageFactoryInterface(): void
    {
        $this->assertInstanceOf(StorageFactoryInterface::class, new JsonStorageFactory());
    }

    public function testCreateReturnsJsonStorageForTheGivenCassette(): void
    {
        vfsStream::setup('testDir');

        $storage = (new JsonStorageFactory())->create(vfsStream::url('testDir').'/', 'test-cassette');

        $this->assertInstanceOf(Json::class, $storage);
        $this->assertTrue($storage->isNew(), 'A freshly created cassette should be new.');
    }
}
