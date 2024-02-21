<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\Json;

final class JsonTest extends TestCase
{
    protected string $filePath;

    protected Json $jsonObject;

    protected function setUp(): void
    {
        vfsStream::setup('test');
        $this->filePath = vfsStream::url('test/').'json_test';
        $this->jsonObject = new Json(vfsStream::url('test/'), 'json_test');
    }

    public function testIterateOneObject(): void
    {
        $this->iterateAndTest(
            '[{"para1": "val1"}]',
            [
                ['para1' => 'val1'],
            ],
            'Single json object was not parsed correctly.'
        );
    }

    public function testIterateTwoObjects(): void
    {
        $this->iterateAndTest(
            '[{"para1": "val1"}, {"para2": "val2"}]',
            [
                ['para1' => 'val1'],
                ['para2' => 'val2'],
            ],
            'Two json objects were not parsed correctly.'
        );
    }

    public function testIterateFirstNestedObject(): void
    {
        $this->iterateAndTest(
            '[{"para1": {"para2": "val2"}}, {"para3": "val3"}]',
            [
                ['para1' => ['para2' => 'val2']],
                ['para3' => 'val3'],
            ],
            'Nested json objects were not parsed correctly.'
        );
    }

    public function testIterateSecondNestedObject(): void
    {
        $this->iterateAndTest(
            '[{"para1": "val1"}, {"para2": {"para3": "val3"}}]',
            [
                ['para1' => 'val1'],
                ['para2' => ['para3' => 'val3']],
            ],
            'Nested json objects were not parsed correctly.'
        );
    }

    public function testIterateEmpty(): void
    {
        $this->iterateAndTest(
            '[]',
            [],
            'Empty json was not parsed correctly.'
        );
    }

    public function testStoreRecording(): void
    {
        $expected = [
            'request' => [
                'some' => 'request',
            ],
            'response' => [
                'some' => 'response',
            ],
        ];

        $this->jsonObject->storeRecording($expected);

        $actual = [];
        foreach ($this->jsonObject as $recording) {
            $actual[] = $recording;
        }

        $this->assertEquals($expected, $actual[0], 'Storing and reading a recording failed.');
    }

    public function testValidJson(): void
    {
        $stored = [
            'request' => 'some request',
            'response' => 'some response',
        ];
        $this->jsonObject->storeRecording($stored);
        $this->jsonObject->storeRecording($stored);

        $this->assertJson((string) file_get_contents($this->filePath));
    }

    public function testStoreRecordingWhenBlankFileAlreadyExists(): void
    {
        vfsStream::create(['blank_file_test' => '']);
        $filePath = vfsStream::url('test/').'blank_file_test';

        $jsonObject = new Json(vfsStream::url('test/'), 'blank_file_test');
        $stored = [
            'request' => 'some request',
            'response' => 'some response',
        ];
        $jsonObject->storeRecording($stored);

        $this->assertJson((string) file_get_contents($filePath));
    }

    /** @param array<mixed> $expected */
    private function iterateAndTest(string $json, $expected, string $message): void
    {
        file_put_contents($this->filePath, $json);

        $actual = [];
        foreach ($this->jsonObject as $object) {
            $actual[] = $object;
        }

        $this->assertEquals($expected, $actual, $message);
    }
}
