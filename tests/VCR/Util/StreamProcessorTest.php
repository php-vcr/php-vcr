<?php

namespace VCR\Util;

class StreamProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers StreamProcessor::__construct()
     */
    public function testInstanceCanBeCreated()
    {
        $streamProcessor = new StreamProcessor();
        $this->assertInstanceOf('\\VCR\\Util\StreamProcessor', $streamProcessor);
    }

    public function getPhpFileFixtures()
    {
        return array(
            array('stream_processor_test.cgi'),
            array('stream_processor_test.inc'),
            array('stream_processor_test.php'),
            array('stream_processor_test.php4'),
            array('stream_processor_test.php5'),
            array('stream_processor_test.phtml'),
        );
    }

    /**
     * @dataProvider getPhpFileFixtures
     */
    public function testInterceptsPhpFiles($exampleFile)
    {
        /* @var \VCR\Util\StreamProcessor */
        $streamProcessor = $this->getMockBuilder('\\VCR\\Util\\StreamProcessor')
            ->setMethods(array('isPhpFile'))
            ->getMock();

        $this->assertTrue($streamProcessor->isPhpFile($exampleFile));
    }

    public function testDoesNotInterceptJsFiles()
    {
        /* @var \VCR\Util\StreamProcessor */
        $streamProcessor = $this->getMockBuilder('\\VCR\\Util\\StreamProcessor')
            ->getMock();

        $streamProcessor
            ->expects($this->never())
            ->method('appendFiltersToStream');

        $streamProcessor->intercept();
        ob_start();
        include __DIR__ . '/../../fixtures/stream_processor_test.js';
        ob_end_clean();
        $streamProcessor->restore();
    }

}
