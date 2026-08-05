<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\Yaml;
use VCR\Storage\YamlStorageFactory;

final class YamlStorageFactoryTest extends TestCase
{
    public function testImplementsStorageFactoryInterface(): void
    {
        $this->assertInstanceOf(StorageFactoryInterface::class, new YamlStorageFactory());
    }

    public function testCreateReturnsYamlStorageForTheGivenCassette(): void
    {
        vfsStream::setup('testDir');

        $storage = (new YamlStorageFactory())->create(vfsStream::url('testDir').'/', 'test-cassette');

        $this->assertInstanceOf(Yaml::class, $storage);
        $this->assertTrue($storage->isNew(), 'A freshly created cassette should be new.');
    }
}
