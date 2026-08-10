<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\EncryptedStorage;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\Storage\Encryption\EncryptionPolicy;
use VCR\Storage\Encryption\SodiumCipher;
use VCR\Storage\PurgeableEncryptedStorage;
use VCR\Storage\PurgeableStorageInterface;
use VCR\Storage\StorageInterface;
use VCR\Storage\Yaml;

final class EncryptedStorageTest extends TestCase
{
    private SodiumCipher $cipher;

    private Yaml $inner;

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        $this->cipher = new SodiumCipher(
            EncryptionKey::fromBinary(str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES))
        );
        $this->inner = new Yaml(vfsStream::url('testDir').'/', 'encrypted_test');
    }

    private function storage(): EncryptedStorage
    {
        return new EncryptedStorage($this->inner, $this->cipher, new EncryptionPolicy());
    }

    private function rawCassette(): string
    {
        return (string) file_get_contents(vfsStream::url('testDir').'/encrypted_test');
    }

    /**
     * @return array<string,mixed>
     */
    private function recording(int $index = 0): array
    {
        return [
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.example.com/login',
                'headers' => ['Authorization' => 'Bearer secret'],
                'body' => 'hunter2',
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
                'body' => 'welcome',
            ],
            'index' => $index,
        ];
    }

    public function testImplementsStorageInterface(): void
    {
        $this->assertInstanceOf(StorageInterface::class, $this->storage());
    }

    public function testWritesCiphertextToTheInnerStorage(): void
    {
        $this->storage()->storeRecording($this->recording());

        $raw = $this->rawCassette();

        $this->assertStringNotContainsString('hunter2', $raw);
        $this->assertStringNotContainsString('Bearer secret', $raw);
        $this->assertStringContainsString('vcr:enc:v1:', $raw);
    }

    public function testUrlAndMethodStayReadableOnDisk(): void
    {
        $this->storage()->storeRecording($this->recording());

        $raw = $this->rawCassette();

        $this->assertStringContainsString('https://api.example.com/login', $raw);
        $this->assertStringContainsString('POST', $raw);
    }

    public function testIteratingReturnsPlaintext(): void
    {
        $storage = $this->storage();
        $storage->storeRecording($this->recording());

        $storage->rewind();
        $storage->valid();

        $this->assertSame($this->recording(), $storage->current());
    }

    public function testIteratingSeveralRecordingsReturnsThemInOrder(): void
    {
        $storage = $this->storage();
        $storage->storeRecording($this->recording(0));
        $storage->storeRecording($this->recording(1));

        $collected = [];
        foreach ($storage as $recording) {
            $collected[] = $recording;
        }

        $this->assertSame([$this->recording(0), $this->recording(1)], $collected);
    }

    public function testKeyIsDelegated(): void
    {
        $storage = $this->storage();
        $storage->storeRecording($this->recording());

        $storage->rewind();
        $storage->next();

        $this->assertSame($this->inner->key(), $storage->key());
    }

    public function testIsNewIsDelegated(): void
    {
        $this->assertSame($this->inner->isNew(), $this->storage()->isNew());
    }

    public function testPurgeableVariantIsAPurgeableStorage(): void
    {
        $storage = new PurgeableEncryptedStorage($this->inner, $this->cipher, new EncryptionPolicy());

        $this->assertInstanceOf(PurgeableStorageInterface::class, $storage);
        $this->assertInstanceOf(EncryptedStorage::class, $storage);
    }

    public function testPurgeClearsTheInnerStorage(): void
    {
        $storage = new PurgeableEncryptedStorage($this->inner, $this->cipher, new EncryptionPolicy());
        $storage->storeRecording($this->recording());

        $storage->purge();
        $storage->rewind();

        $this->assertFalse($storage->valid());
    }

    public function testPlainVariantIsNotPurgeable(): void
    {
        $this->assertNotInstanceOf(PurgeableStorageInterface::class, $this->storage());
    }
}
