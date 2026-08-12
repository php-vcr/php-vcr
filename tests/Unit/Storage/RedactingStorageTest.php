<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\RedactingStorage;
use VCR\Storage\Redaction\RedactionRules;
use VCR\Storage\Redaction\Rule\ValueSubstitutionRule;
use VCR\Storage\Redaction\Scope;
use VCR\Storage\Yaml;

final class RedactingStorageTest extends TestCase
{
    private Yaml $inner;

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        $this->inner = new Yaml(vfsStream::url('testDir').'/', 'redacted_test');
    }

    private function storage(RedactionRules $rules): RedactingStorage
    {
        return new RedactingStorage($this->inner, $rules);
    }

    private function rawCassette(): string
    {
        return (string) file_get_contents(vfsStream::url('testDir').'/redacted_test');
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
                'headers' => ['Set-Cookie' => 'session=abc123'],
                'body' => 'welcome',
            ],
            'index' => $index,
        ];
    }

    public function testWritesRedactedRecordingToTheInnerStorage(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('REDACTED', 'hunter2');

        $this->storage($rules)->storeRecording($this->recording());

        $raw = $this->rawCassette();

        $this->assertStringNotContainsString('hunter2', $raw);
        $this->assertStringContainsString('REDACTED', $raw);
    }

    public function testUrlAndMethodStayReadableOnDisk(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('REDACTED', 'hunter2');

        $this->storage($rules)->storeRecording($this->recording());

        $raw = $this->rawCassette();

        $this->assertStringContainsString('https://api.example.com/login', $raw);
        $this->assertStringContainsString('POST', $raw);
    }

    public function testRestoresOnRead(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('REDACTED', 'hunter2');
        $storage = $this->storage($rules);
        $storage->storeRecording($this->recording());

        $storage->rewind();
        $storage->valid();

        $this->assertSame($this->recording(), $storage->current());
    }

    public function testIrreversibleResponseHeaderIsNotRestored(): void
    {
        $rules = RedactionRules::create()->allHeaders(Scope::RESPONSE);
        $storage = $this->storage($rules);
        $storage->storeRecording($this->recording());

        $storage->rewind();
        $storage->valid();

        $current = $storage->current();
        $this->assertIsArray($current);
        $this->assertSame('', $current['response']['headers']['Set-Cookie']);
    }

    public function testReverseOrderRestorationMattersForChainedRules(): void
    {
        $rules = RedactionRules::create()
            ->add(new ValueSubstitutionRule('bar', 'foo'))
            ->add(new ValueSubstitutionRule('baz', 'bar'));

        $recording = $this->recording();
        $recording['request']['body'] = 'foo';

        $storage = $this->storage($rules);
        $storage->storeRecording($recording);

        $raw = $this->rawCassette();
        $this->assertStringContainsString('baz', $raw);

        $storage->rewind();
        $storage->valid();

        $current = $storage->current();
        $this->assertIsArray($current);
        $this->assertSame('foo', $current['request']['body']);
    }

    public function testIteratingSeveralRecordingsReturnsThemInOrder(): void
    {
        $rules = RedactionRules::create()->filterSensitiveData('REDACTED', 'hunter2');
        $storage = $this->storage($rules);
        $storage->storeRecording($this->recording(0));
        $storage->storeRecording($this->recording(1));

        $collected = [];
        foreach ($storage as $recording) {
            $collected[] = $recording;
        }

        $this->assertSame([$this->recording(0), $this->recording(1)], $collected);
    }

    public function testDelegatesKeyToTheInnerStorage(): void
    {
        $storage = $this->storage(RedactionRules::create());
        $storage->storeRecording($this->recording());

        $storage->rewind();
        $storage->next();

        $this->assertSame($this->inner->key(), $storage->key());
    }

    public function testIsNewIsDelegated(): void
    {
        $this->assertSame($this->inner->isNew(), $this->storage(RedactionRules::create())->isNew());
    }

    public function testPlainVariantIsNotPurgeable(): void
    {
        $this->assertNotInstanceOf(\VCR\Storage\PurgeableStorageInterface::class, $this->storage(RedactionRules::create()));
    }
}
