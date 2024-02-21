<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VCR\Util\StreamProcessor;

final class StreamProcessorTest extends TestCase
{
    /**
     * test flock with file_put_contents.
     */
    public function testFlockWithFilePutContents(): void
    {
        $processor = new StreamProcessor();
        $processor->intercept();

        $testData = 'test data';
        $testFilePath = 'tests/fixtures/file_put_contents';
        $res = file_put_contents($testFilePath, $testData, \LOCK_EX);
        unlink($testFilePath);

        $processor->restore();
        $this->assertEquals(\strlen($testData), $res);
    }

    /**
     * @dataProvider streamOpenAppendFilterProvider
     */
    public function testStreamOpenShouldAppendFilters(bool $expected, int $option, bool $shouldProcess = null): void
    {
        $mock = $this->getMockBuilder('VCR\Util\StreamProcessor')
            ->disableOriginalConstructor()
            ->setMethods(['intercept', 'restore', 'appendFiltersToStream', 'shouldProcess'])
            ->getMock();

        if (null !== $shouldProcess) {
            $mock->expects($this->once())->method('shouldProcess')->willReturn($shouldProcess);
        }

        if ($expected) {
            $mock->expects($this->once())->method('appendFiltersToStream');
        } else {
            $mock->expects($this->never())->method('appendFiltersToStream');
        }

        $mock->stream_open('tests/fixtures/streamprocessor_data', 'r', $option, $fullPath);
        $mock->stream_close();
    }

    /** @return array<array<bool|int>> */
    public function streamOpenAppendFilterProvider(): array
    {
        return [
            [true, StreamProcessor::STREAM_OPEN_FOR_INCLUDE, true],
            [false, StreamProcessor::STREAM_OPEN_FOR_INCLUDE, false],
            [false, 0],
        ];
    }

    /** @return array<string[]> */
    public function streamOpenFileModesWhichDoNotCreateFiles(): array
    {
        return [
            ['r'],
            ['rb'],
            ['rt'],
            ['r+'],
        ];
    }

    /**
     * @dataProvider streamOpenFileModesWhichDoNotCreateFiles
     */
    public function testStreamOpenShouldNotFailOnNonExistingFile(string $fileMode): void
    {
        $test = $this;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($test): void {
            $test->fail('should not throw errors');
        });

        $processor = new StreamProcessor();

        $result = $processor->stream_open('tests/fixtures/unknown', $fileMode, StreamProcessor::STREAM_OPEN_FOR_INCLUDE, $fullPath);
        $this->assertFalse($result);

        restore_error_handler();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testUrlStatSuccessfully(): void
    {
        $test = $this;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($test): void {
            $test->fail('should not throw errors');
        });

        $processor = new StreamProcessor();
        $processor->url_stat('tests/fixtures/streamprocessor_data', 0);

        restore_error_handler();
    }

    public function testUrlStatFileNotFound(): void
    {
        $processor = new StreamProcessor();
        $this->expectWarning();
        $processor->url_stat('file_not_found', 0);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testQuietUrlStatFileNotFoundToBeQuiet(): void
    {
        $processor = new StreamProcessor();
        $processor->url_stat('file_not_found', \STREAM_URL_STAT_QUIET);
    }

    public function testDirOpendir(): void
    {
        $processor = new StreamProcessor();
        $this->assertTrue($processor->dir_opendir('tests/fixtures'));
        $processor->dir_closedir();
    }

    public function testDirOpendirNotFound(): void
    {
        $test = $this;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($test): bool {
            $test->assertStringContainsString('opendir(not_found', $errstr);

            return true;
        });

        $processor = new StreamProcessor();
        $this->assertFalse($processor->dir_opendir('not_found'));

        restore_error_handler();
    }

    public function testMakeDir(): void
    {
        $mock = $this->getStreamProcessorMock();
        $mock->expects($this->exactly(2))->method('restore');
        $mock->expects($this->exactly(2))->method('intercept');

        $this->assertTrue($mock->mkdir('tests/fixtures/unittest_streamprocessor', 0777, 0));
        $this->assertTrue($mock->rmdir('tests/fixtures/unittest_streamprocessor'));
    }

    public function testRename(): void
    {
        $mock = $this->getStreamProcessorMock();
        $mock->expects($this->exactly(3))->method('restore');
        $mock->expects($this->exactly(3))->method('intercept');

        $this->assertTrue($mock->mkdir('tests/fixtures/unittest_streamprocessor', 0777, 0));
        $this->assertTrue($mock->rename('tests/fixtures/unittest_streamprocessor', 'tests/fixtures/sp'));
        $this->assertTrue($mock->rmdir('tests/fixtures/sp'));
    }

    public function testStreamMetadata(): void
    {
        if (!\function_exists('posix_getuid')) {
            $this->markTestSkipped('Requires "posix_getuid" function.');
        }

        $mock = $this->getStreamProcessorMock();
        $mock->expects($this->exactly(8))->method('restore');
        $mock->expects($this->exactly(8))->method('intercept');

        $path = 'tests/fixtures/unnitest_streamprocessor_metadata';
        $this->assertTrue($mock->stream_metadata($path, \STREAM_META_TOUCH, null));
        $this->assertTrue($mock->stream_metadata($path, \STREAM_META_TOUCH, [time(), time()]));

        $this->assertTrue($mock->stream_metadata($path, \STREAM_META_OWNER_NAME, posix_getuid()));
        $this->assertTrue($mock->stream_metadata($path, \STREAM_META_OWNER, posix_getuid()));

        $this->assertTrue($mock->stream_metadata($path, \STREAM_META_GROUP_NAME, posix_getgid()));
        $this->assertTrue($mock->stream_metadata($path, \STREAM_META_GROUP, posix_getgid()));

        $this->assertTrue($mock->stream_metadata($path, \STREAM_META_ACCESS, 0777));

        $this->assertTrue($mock->unlink($path));
    }

    /** @return StreamProcessor&MockObject */
    protected function getStreamProcessorMock()
    {
        return $this->getMockBuilder(StreamProcessor::class)
            ->disableOriginalConstructor()
            ->setMethods(['intercept', 'restore'])
            ->getMock();
    }
}
