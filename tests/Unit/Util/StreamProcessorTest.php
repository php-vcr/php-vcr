<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VCR\Util\StreamProcessor;

/**
 * Simulates an environment in which routing the existence check back through the
 * VCR wrapper's own url_stat() makes an existing file look missing (issue #424).
 */
final class BrokenUrlStatStreamProcessor extends StreamProcessor
{
    public function url_stat(string $path, int $flags)
    {
        return false;
    }
}

/**
 * Exposes the protected interception state so tests can assert the wrapper is
 * never left restored after an early return in stream_open().
 */
final class InterceptStateStreamProcessor extends StreamProcessor
{
    public function isIntercepting(): bool
    {
        return $this->isIntercepting;
    }
}

final class StreamProcessorTest extends TestCase
{
    /**
     * When this wrapper is intercepting, including an existing file must still
     * succeed even if this wrapper's own url_stat() would report it as missing,
     * because the existence check has to run against the native filesystem.
     *
     * @see https://github.com/php-vcr/php-vcr/issues/424
     */
    public function testStreamOpenChecksExistenceAgainstNativeFilesystem(): void
    {
        $processor = new BrokenUrlStatStreamProcessor();
        $processor->intercept();

        try {
            // A path containing ".." just like Composer's classmap autoloader produces.
            $path = __DIR__.'/../../fixtures/../fixtures/streamprocessor_include_target.php';
            $result = include $path;
        } finally {
            $processor->restore();
        }

        $this->assertNotFalse($result, 'Including an existing file must not fail while VCR is intercepting.');
        $this->assertTrue(class_exists(\VCR\Tests\Fixtures\StreamProcessorIncludeTarget::class, false));
    }

    /**
     * A missing file in read mode returns false, but the wrapper must re-intercept
     * before returning so it is never left in an inconsistent (restored) state.
     *
     * @see https://github.com/php-vcr/php-vcr/issues/424
     */
    public function testStreamOpenStaysInterceptingAfterMissingRead(): void
    {
        $processor = new InterceptStateStreamProcessor();
        $processor->intercept();

        try {
            $result = $processor->stream_open('tests/fixtures/unknown', 'r', StreamProcessor::STREAM_OPEN_FOR_INCLUDE, $fullPath);
            $this->assertFalse($result);
            $this->assertTrue($processor->isIntercepting(), 'The wrapper must stay intercepting after a missing read.');
        } finally {
            $processor->restore();
        }
    }

    /**
     * @see https://github.com/php-vcr/php-vcr/issues/350
     */
    public function testRestoreResetsInterceptingState(): void
    {
        $processor = new InterceptStateStreamProcessor();
        $processor->intercept();
        $this->assertTrue($processor->isIntercepting(), 'Sanity: intercept() must set the flag.');

        $processor->restore();

        $this->assertFalse(
            $processor->isIntercepting(),
            'restore() must reset isIntercepting so a later intercept() re-registers the wrapper.'
        );
    }

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

    public function testSetStreamOptions(): void
    {
        $processor = new StreamProcessor();
        $processor->intercept();

        $handle = fopen('tests/fixtures/file_put_contents', 'w');

        self::assertTrue(stream_set_blocking($handle, true));
        self::assertFalse(stream_set_timeout($handle, 10));
        self::assertFalse(stream_set_timeout($handle, 5, 2));
        self::assertSame(-1, stream_set_write_buffer($handle, 0));
        self::assertSame(0, stream_set_read_buffer($handle, 0));

        fclose($handle);
        unlink('tests/fixtures/file_put_contents');

        $processor->restore();
    }

    /**
     * @dataProvider streamOpenAppendFilterProvider
     */
    public function testStreamOpenShouldAppendFilters(bool $expected, int $option, ?bool $shouldProcess = null): void
    {
        $mock = $this->getMockBuilder('VCR\Util\StreamProcessor')
            ->disableOriginalConstructor()
            ->onlyMethods(['intercept', 'restore', 'appendFiltersToStream', 'shouldProcess'])
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
    public static function streamOpenAppendFilterProvider(): array
    {
        return [
            [true, StreamProcessor::STREAM_OPEN_FOR_INCLUDE, true],
            [false, StreamProcessor::STREAM_OPEN_FOR_INCLUDE, false],
            [false, 0],
        ];
    }

    /** @return array<string[]> */
    public static function streamOpenFileModesWhichDoNotCreateFiles(): array
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
        set_error_handler(static function ($errno, $errstr, $errfile, $errline) use ($test): void {
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
        set_error_handler(static function ($errno, $errstr, $errfile, $errline) use ($test): void {
            $test->fail('should not throw errors');
        });

        $processor = new StreamProcessor();
        $processor->url_stat('tests/fixtures/streamprocessor_data', 0);

        restore_error_handler();
    }

    public function testUrlStatFileNotFound(): void
    {
        $processor = new StreamProcessor();

        set_error_handler(static function (
            int $errno,
            string $errstr,
            string $errfile = '',
            int $errline = 0
        ): void {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }, \E_WARNING);

        try {
            $this->expectException(\ErrorException::class);
            $processor->url_stat('file_not_found', 0);
        } finally {
            restore_error_handler();
        }
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
        set_error_handler(static function ($errno, $errstr, $errfile, $errline) use ($test): bool {
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
            ->onlyMethods(['intercept', 'restore'])
            ->getMock();
    }
}
