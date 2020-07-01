<?php

namespace VCR\Util;

use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

class StreamProcessorTest extends TestCase
{
    /**
     * test flock with file_put_contents.
     */
    public function testFlockWithFilePutContents()
    {
        $processor = new StreamProcessor();
        $processor->intercept();

        $testData = 'test data';
        $testFilePath = 'tests/fixtures/file_put_contents';
        $res = file_put_contents($testFilePath, $testData, LOCK_EX);
        unlink($testFilePath);

        $processor->restore();
        $this->assertEquals(\strlen($testData), $res);
    }

    /**
     * @dataProvider streamOpenAppendFilterProvider
     *
     * @param bool $expected
     * @param bool $shouldProcess
     * @param int  $option
     */
    public function testStreamOpenShouldAppendFilters($expected, $option, $shouldProcess = null)
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

    public function streamOpenAppendFilterProvider()
    {
        return [
            [true, StreamProcessor::STREAM_OPEN_FOR_INCLUDE, true],
            [false, StreamProcessor::STREAM_OPEN_FOR_INCLUDE, false],
            [false, 0],
        ];
    }

    public function streamOpenFileModesWhichDoNotCreateFiles()
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
    public function testStreamOpenShouldNotFailOnNonExistingFile($fileMode)
    {
        $test = $this;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($test) {
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
    public function testUrlStatSuccessfully()
    {
        $test = $this;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($test) {
            $test->fail('should not throw errors');
        });

        $processor = new StreamProcessor();
        $processor->url_stat('tests/fixtures/streamprocessor_data', 0);

        restore_error_handler();
    }

    public function testUrlStatFileNotFound()
    {
        $processor = new StreamProcessor();
        $this->expectException(Warning::class);
        $processor->url_stat('file_not_found', 0);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testQuietUrlStatFileNotFoundToBeQuiet()
    {
        $processor = new StreamProcessor();
        $processor->url_stat('file_not_found', STREAM_URL_STAT_QUIET);
    }

    public function testDirOpendir()
    {
        $processor = new StreamProcessor();
        $this->assertTrue($processor->dir_opendir('tests/fixtures'));
        $processor->dir_closedir();
    }

    public function testDirOpendirNotFound()
    {
        $test = $this;
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($test) {
            $test->assertContains('opendir(not_found', $errstr);
        });

        $processor = new StreamProcessor();
        $this->assertFalse($processor->dir_opendir('not_found'));

        restore_error_handler();
    }

    public function testMakeDir()
    {
        $mock = $this->getStreamProcessorMock();
        $mock->expects($this->exactly(2))->method('restore');
        $mock->expects($this->exactly(2))->method('intercept');

        $this->assertTrue($mock->mkdir('tests/fixtures/unittest_streamprocessor', 0777, false));
        $this->assertTrue($mock->rmdir('tests/fixtures/unittest_streamprocessor'));
    }

    public function testRename()
    {
        $mock = $this->getStreamProcessorMock();
        $mock->expects($this->exactly(3))->method('restore');
        $mock->expects($this->exactly(3))->method('intercept');

        $this->assertTrue($mock->mkdir('tests/fixtures/unittest_streamprocessor', 0777, false));
        $this->assertTrue($mock->rename('tests/fixtures/unittest_streamprocessor', 'tests/fixtures/sp'));
        $this->assertTrue($mock->rmdir('tests/fixtures/sp'));
    }

    public function testStreamMetadata()
    {
        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $this->markTestSkipped('Behavior is only applicable and testable for PHP 5.4+');
        }

        if (!\function_exists('posix_getuid')) {
            $this->markTestSkipped('Requires "posix_getuid" function.');
        }

        $mock = $this->getStreamProcessorMock();
        $mock->expects($this->exactly(8))->method('restore');
        $mock->expects($this->exactly(8))->method('intercept');

        $path = 'tests/fixtures/unnitest_streamprocessor_metadata';
        $this->assertTrue($mock->stream_metadata($path, STREAM_META_TOUCH, null));
        $this->assertTrue($mock->stream_metadata($path, STREAM_META_TOUCH, [time(), time()]));

        $this->assertTrue($mock->stream_metadata($path, STREAM_META_OWNER_NAME, posix_getuid()));
        $this->assertTrue($mock->stream_metadata($path, STREAM_META_OWNER, posix_getuid()));

        $this->assertTrue($mock->stream_metadata($path, STREAM_META_GROUP_NAME, posix_getgid()));
        $this->assertTrue($mock->stream_metadata($path, STREAM_META_GROUP, posix_getgid()));

        $this->assertTrue($mock->stream_metadata($path, STREAM_META_ACCESS, 0777));

        $this->assertTrue($mock->unlink($path));
    }

    protected function getStreamProcessorMock()
    {
        return $this->getMockBuilder('VCR\Util\StreamProcessor')
            ->disableOriginalConstructor()
            ->setMethods(['intercept', 'restore'])
            ->getMock();
    }
}
