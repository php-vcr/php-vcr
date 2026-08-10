<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\EncryptedStorage;
use VCR\Storage\EncryptedStorageFactory;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\Storage\Encryption\EncryptionPolicy;
use VCR\Storage\Encryption\SodiumCipher;
use VCR\Storage\JsonStorageFactory;
use VCR\Storage\PurgeableEncryptedStorage;
use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\YamlStorageFactory;

final class EncryptedStorageFactoryTest extends TestCase
{
    private EncryptionKey $key;

    protected function setUp(): void
    {
        if (!\extension_loaded('sodium')) {
            $this->markTestSkipped('The encrypted storage requires ext-sodium, which is not loaded.');
        }

        vfsStream::setup('testDir');
        $this->key = EncryptionKey::fromBinary(str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES));
    }

    public function testImplementsStorageFactoryInterface(): void
    {
        $factory = EncryptedStorageFactory::withKey(new YamlStorageFactory(), $this->key);

        $this->assertInstanceOf(StorageFactoryInterface::class, $factory);
    }

    public function testWrapsAPurgeableStorageInThePurgeableVariant(): void
    {
        $factory = EncryptedStorageFactory::withKey(new YamlStorageFactory(), $this->key);

        $storage = $factory->create(vfsStream::url('testDir').'/', 'yaml-cassette');

        $this->assertInstanceOf(PurgeableEncryptedStorage::class, $storage);
    }

    public function testWorksOverJsonStorageAsWell(): void
    {
        $factory = EncryptedStorageFactory::withKey(new JsonStorageFactory(), $this->key);

        $storage = $factory->create(vfsStream::url('testDir').'/', 'json-cassette');

        $this->assertInstanceOf(EncryptedStorage::class, $storage);
    }

    public function testAcceptsAnExplicitCipherAndPolicy(): void
    {
        $factory = new EncryptedStorageFactory(
            new YamlStorageFactory(),
            new SodiumCipher($this->key),
            new EncryptionPolicy(['response.body'], [])
        );

        $storage = $factory->create(vfsStream::url('testDir').'/', 'explicit-cassette');

        $this->assertInstanceOf(EncryptedStorage::class, $storage);
    }

    public function testCreatedStorageWrapsTheGivenCassette(): void
    {
        $factory = EncryptedStorageFactory::withKey(new YamlStorageFactory(), $this->key);

        $storage = $factory->create(vfsStream::url('testDir').'/', 'fresh-cassette');

        $this->assertTrue($storage->isNew(), 'A freshly created cassette should be new.');
    }

    public function testEachCassetteGetsItsOwnStorage(): void
    {
        $factory = EncryptedStorageFactory::withKey(new YamlStorageFactory(), $this->key);

        $first = $factory->create(vfsStream::url('testDir').'/', 'first-cassette');
        $second = $factory->create(vfsStream::url('testDir').'/', 'second-cassette');

        $this->assertNotSame($first, $second);
    }
}
