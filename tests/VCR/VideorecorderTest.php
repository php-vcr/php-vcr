<?php

namespace VCR;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test Videorecorder.
 */
class VideorecorderTest extends TestCase
{
    public function testCreateVideorecorder()
    {
        $this->assertInstanceOf(
            '\VCR\Videorecorder',
            new Videorecorder(new Configuration(), new Util\HttpClient(), VCRFactory::getInstance())
        );
    }

    public function testInsertCassetteEjectExisting()
    {
        vfsStream::setup('testDir');
        $factory = VCRFactory::getInstance();
        $configuration = $factory->get('VCR\Configuration');
        $configuration->setCassettePath(vfsStream::url('testDir'));
        $configuration->enableLibraryHooks([]);
        $videorecorder = $this->getMockBuilder('\VCR\Videorecorder')
            ->setConstructorArgs([$configuration, new Util\HttpClient(), VCRFactory::getInstance()])
            ->setMethods(['eject'])
            ->getMock();

        $videorecorder->expects($this->exactly(2))->method('eject');

        $videorecorder->turnOn();
        $videorecorder->insertCassette('cassette1');
        $videorecorder->insertCassette('cassette2');
        $videorecorder->turnOff();
    }

    public function testHandleRequestRecordsRequestWhenModeIsNewRecords()
    {
        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response(200, [], 'example response');
        $client = $this->getClientMock($request, $response);
        $configuration = new Configuration();
        $configuration->enableLibraryHooks([]);
        $configuration->setMode('new_episodes');

        $videorecorder = new class($configuration, $client, VCRFactory::getInstance()) extends Videorecorder {
            public function setCassette(Cassette $cassette): void
            {
                $this->cassette = $cassette;
            }
        };

        $videorecorder->setCassette($this->getCassetteMock($request, $response));

        $this->assertEquals($response, $videorecorder->handleRequest($request));
    }

    public function testHandleRequestThrowsExceptionWhenModeIsNone()
    {
        $this->expectException(
            'LogicException',
            "The request does not match a previously recorded request and the 'mode' is set to 'none'. "
            ."If you want to send the request anyway, make sure your 'mode' is set to 'new_episodes'."
        );

        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response(200, [], 'example response');
        $client = $this->getMockBuilder('\VCR\Util\HttpClient')->getMock();
        $configuration = new Configuration();
        $configuration->enableLibraryHooks([]);
        $configuration->setMode('none');

        $videorecorder = new class($configuration, $client, VCRFactory::getInstance()) extends Videorecorder {
            public function setCassette(Cassette $cassette): void
            {
                $this->cassette = $cassette;
            }
        };

        $videorecorder->setCassette($this->getCassetteMock($request, $response, 'none'));

        $videorecorder->handleRequest($request);
    }

    public function testHandleRequestRecordsRequestWhenModeIsOnceAndCassetteIsNew()
    {
        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response(200, [], 'example response');
        $client = $this->getClientMock($request, $response);
        $configuration = new Configuration();
        $configuration->enableLibraryHooks([]);
        $configuration->setMode('once');

        $videorecorder = new class($configuration, $client, VCRFactory::getInstance()) extends Videorecorder {
            public function setCassette(Cassette $cassette): void
            {
                $this->cassette = $cassette;
            }
        };

        $videorecorder->setCassette($this->getCassetteMock($request, $response, 'once', true));

        $this->assertEquals($response, $videorecorder->handleRequest($request));
    }

    public function testHandleRequestThrowsExceptionWhenModeIsOnceAndCassetteIsOld()
    {
        $this->expectException(
            'LogicException',
            "The request does not match a previously recorded request and the 'mode' is set to 'once'. "
            ."If you want to send the request anyway, make sure your 'mode' is set to 'new_episodes'."
        );

        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response(200, [], 'example response');
        $client = $this->getMockBuilder('\VCR\Util\HttpClient')->getMock();
        $configuration = new Configuration();
        $configuration->enableLibraryHooks([]);
        $configuration->setMode('once');

        $videorecorder = new class($configuration, $client, VCRFactory::getInstance()) extends Videorecorder {
            public function setCassette(Cassette $cassette): void
            {
                $this->cassette = $cassette;
            }
        };

        $videorecorder->setCassette($this->getCassetteMock($request, $response, 'once', false));

        $videorecorder->handleRequest($request);
    }

    protected function getClientMock($request, $response)
    {
        $client = $this->getMockBuilder('\VCR\Util\HttpClient')->setMethods(['send'])->getMock();
        $client
            ->expects($this->once())
            ->method('send')
            ->with($request)
            ->willReturn($response);

        return $client;
    }

    protected function getCassetteMock($request, $response, $mode = VCR::MODE_NEW_EPISODES, $isNew = false)
    {
        $cassette = $this->getMockBuilder('\VCR\Cassette')
            ->disableOriginalConstructor()
            ->setMethods(['record', 'playback', 'isNew', 'getName'])
            ->getMock();
        $cassette
            ->expects($this->once())
            ->method('playback')
            ->with($request)
            ->willReturn(null);
        $cassette
            ->method('getName')
            ->willReturn('foobar');

        if (VCR::MODE_NEW_EPISODES === $mode || VCR::MODE_ONCE === $mode && true === $isNew) {
            $cassette
                ->expects($this->once())
                ->method('record')
                ->with($request, $response);
        }

        if ('once' == $mode) {
            $cassette
                ->expects($this->once())
                ->method('isNew')
                ->willReturn($isNew);
        }

        return $cassette;
    }
}
