<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\PurgeableRedactingStorage;
use VCR\Storage\Redaction\RedactionRules;
use VCR\Storage\Yaml;

final class PurgeableRedactingStorageTest extends TestCase
{
    private Yaml $inner;

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        $this->inner = new Yaml(vfsStream::url('testDir').'/', 'redacted_purgeable_test');
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

    public function testPurgeClearsTheInnerStorage(): void
    {
        $storage = new PurgeableRedactingStorage($this->inner, RedactionRules::create());
        $storage->storeRecording($this->recording());

        $storage->purge();
        $storage->rewind();

        $this->assertFalse($storage->valid());
    }
}
