<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\PurgeableStorage;
use VCR\Storage\Yaml;

final class YamlTest extends TestCase
{
    private string $filePath;

    private Yaml $yamlObject;

    protected function setUp(): void
    {
        vfsStream::setup('test');
        $this->filePath = vfsStream::url('test/').\DIRECTORY_SEPARATOR.'yaml_test';
        $this->yamlObject = new Yaml(vfsStream::url('test/'), 'yaml_test');
    }

    public function testIterateOneObject(): void
    {
        $this->iterateAndTest(
            '-'."\n"
            .'    para1: val1',
            [
                ['para1' => 'val1'],
            ],
            'Single yaml object was not parsed correctly.'
        );
    }

    public function testIterateTwoObjects(): void
    {
        $this->iterateAndTest(
            '-'."\n"
            .'    para1: val1'."\n"
            .'-'."\n"
            .'   para2: val2',
            [
                ['para1' => 'val1'],
                ['para2' => 'val2'],
            ],
            'Two yaml objects were not parsed correctly.'
        );
    }

    public function testIterateFirstNestedObject(): void
    {
        $this->iterateAndTest(
            '-'."\n"
            .'    para1:'."\n"
            .'        para2: val2'."\n"
            .'-'."\n"
            .'    para3: val3',
            [
                ['para1' => ['para2' => 'val2']],
                ['para3' => 'val3'],
            ],
            'Nested yaml objects were not parsed correctly.'
        );
    }

    public function testIterateSecondNestedObject(): void
    {
        $this->iterateAndTest(
            '-'."\n"
            .'    para1: val1'."\n"
            .'-'."\n"
            .'    para2:'."\n"
            .'        para3: val3'."\n",
            [
                ['para1' => 'val1'],
                ['para2' => ['para3' => 'val3']],
            ],
            'Nested yaml objects were not parsed correctly.'
        );
    }

    public function testIterateEmpty(): void
    {
        $this->iterateAndTest(
            '',
            [],
            'Empty yaml was not parsed correctly.'
        );
    }

    public function testStoreRecording(): void
    {
        $expected = [
            'request' => 'some request',
            'response' => 'some response',
        ];

        $this->yamlObject->storeRecording($expected);

        $actual = [];
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }

        $this->assertEquals($expected, $actual[0], 'Storing and reading a recording failed.');
    }

    public function testStoreTwoRecording(): void
    {
        $expected = [
            'request' => ['headers' => ['Content-Type' => 'application/json']],
            'response' => ['body' => 'ok', 'status' => 200],
        ];

        $this->yamlObject->storeRecording($expected);
        $this->yamlObject->storeRecording($expected);

        $actual = [];
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }
        $this->assertCount(2, $actual, 'More that two recordings stores.');
        $this->assertEquals($expected, $actual[0], 'Storing and reading first recording failed.');
        $this->assertEquals($expected, $actual[1], 'Storing and reading second recording failed.');
    }

    public function testImplementsPurgeableStorage(): void
    {
        $this->assertInstanceOf(PurgeableStorage::class, $this->yamlObject);
    }

    public function testPurgeEmptiesStorage(): void
    {
        $this->yamlObject->storeRecording(['request' => 'r', 'response' => 's']);

        $before = [];
        foreach ($this->yamlObject as $recording) {
            $before[] = $recording;
        }
        $this->assertCount(1, $before, 'Storage should contain the recording before purge.');

        $this->yamlObject->purge();

        $actual = [];
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }
        $this->assertEmpty($actual);
        $this->assertTrue($this->yamlObject->isNew());
    }

    public function testPurgeAllowsSubsequentRecording(): void
    {
        $this->yamlObject->storeRecording(['request' => 'old', 'response' => 'old']);
        $this->yamlObject->purge();

        $expected = ['request' => 'new', 'response' => 'new'];
        $this->yamlObject->storeRecording($expected);

        $actual = [];
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }
        $this->assertCount(1, $actual);
        $this->assertEquals($expected, $actual[0]);
    }

    /** @param array<mixed> $expected */
    private function iterateAndTest(string $yaml, array $expected, string $message): void
    {
        file_put_contents($this->filePath, $yaml);

        $actual = [];
        foreach ($this->yamlObject as $object) {
            $actual[] = $object;
        }

        $this->assertEquals($expected, $actual, $message);
    }
}
