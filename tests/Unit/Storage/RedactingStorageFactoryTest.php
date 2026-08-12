<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\PurgeableRedactingStorage;
use VCR\Storage\RedactingStorage;
use VCR\Storage\RedactingStorageFactory;
use VCR\Storage\Redaction\RedactionRules;
use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\StorageInterface;
use VCR\Storage\YamlStorageFactory;

final class RedactingStorageFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        vfsStream::setup('testDir');
    }

    public function testWithRulesDelegatesTheGivenRulesToTheCreatedStorage(): void
    {
        $rules = RedactionRules::create()->header('Authorization', 'redacted');
        $factory = RedactingStorageFactory::withRules(new YamlStorageFactory(), $rules);

        $storage = $factory->create(vfsStream::url('testDir').'/', 'rules-cassette');

        $recording = ['request' => ['headers' => ['Authorization' => 'Bearer secret-token']]];
        $storage->storeRecording($recording);
        $storage->rewind();
        $storage->valid();

        $current = $storage->current();
        $this->assertSame('redacted', $current['request']['headers']['Authorization'] ?? null);
    }

    public function testWithDefaultsCoversExactlyTheThreeStandardSensitiveResponseHeaders(): void
    {
        $factory = RedactingStorageFactory::withDefaults(new YamlStorageFactory());

        $storage = $factory->create(vfsStream::url('testDir').'/', 'defaults-cassette');

        $recording = [
            'response' => [
                'headers' => [
                    'Set-Cookie' => 'session=abc123',
                    'WWW-Authenticate' => 'Basic realm="example"',
                    'Proxy-Authenticate' => 'Basic realm="proxy"',
                    'Content-Type' => 'application/json',
                ],
            ],
        ];
        $storage->storeRecording($recording);
        $storage->rewind();
        $storage->valid();

        $current = $storage->current();
        $this->assertSame('', $current['response']['headers']['Set-Cookie'] ?? null);
        $this->assertSame('', $current['response']['headers']['WWW-Authenticate'] ?? null);
        $this->assertSame('', $current['response']['headers']['Proxy-Authenticate'] ?? null);
        $this->assertSame('application/json', $current['response']['headers']['Content-Type'] ?? null);
    }

    public function testWrapsAPurgeableStorageInThePurgeableVariant(): void
    {
        $factory = RedactingStorageFactory::withDefaults(new YamlStorageFactory());

        $storage = $factory->create(vfsStream::url('testDir').'/', 'yaml-cassette');

        $this->assertInstanceOf(PurgeableRedactingStorage::class, $storage);
    }

    public function testWrapsANonPurgeableStorageInThePlainVariant(): void
    {
        $factory = RedactingStorageFactory::withDefaults(new NonPurgeableStorageFactoryStub());

        $storage = $factory->create(vfsStream::url('testDir').'/', 'non-purgeable-cassette');

        $this->assertInstanceOf(RedactingStorage::class, $storage);
        $this->assertNotInstanceOf(PurgeableRedactingStorage::class, $storage);
    }

    public function testEachCassetteGetsItsOwnStorage(): void
    {
        $factory = RedactingStorageFactory::withDefaults(new YamlStorageFactory());

        $first = $factory->create(vfsStream::url('testDir').'/', 'first-cassette');
        $second = $factory->create(vfsStream::url('testDir').'/', 'second-cassette');

        $this->assertNotSame($first, $second);
    }
}

final class NonPurgeableStorageFactoryStub implements StorageFactoryInterface
{
    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        return new NonPurgeableStorageStub();
    }
}

final class NonPurgeableStorageStub implements StorageInterface
{
    /**
     * @var array<string,mixed>|null
     */
    private $recording;

    public function storeRecording(array $recording): void
    {
        $this->recording = $recording;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function current(): ?array
    {
        return $this->recording;
    }

    public function next(): void
    {
    }

    public function key(): int
    {
        return 0;
    }

    public function rewind(): void
    {
    }

    public function valid(): bool
    {
        return null !== $this->recording;
    }

    public function isNew(): bool
    {
        return true;
    }
}
