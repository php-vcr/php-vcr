<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Request;
use VCR\Response;
use VCR\Storage\Yaml;
use VCR\Util\HttpClient;
use VCR\VCR;
use VCR\VCRFactory;
use VCR\Videorecorder;

final class VideorecorderTest extends TestCase
{
    public function testCreateVideorecorder(): void
    {
        $this->assertInstanceOf(
            Videorecorder::class,
            new Videorecorder(new Configuration(), new HttpClient(), VCRFactory::getInstance())
        );
    }

    public function testInsertCassetteEjectExisting(): void
    {
        vfsStream::setup('testDir');
        $factory = VCRFactory::getInstance();
        $configuration = $factory->get('VCR\Configuration');
        $configuration->setCassettePath(vfsStream::url('testDir'));
        $configuration->enableLibraryHooks([]);
        $videorecorder = $this->getMockBuilder('\VCR\Videorecorder')
            ->setConstructorArgs([$configuration, new HttpClient(), VCRFactory::getInstance()])
            ->setMethods(['eject', 'resetIndex'])
            ->getMock();

        $videorecorder->expects($this->exactly(2))->method('eject');
        $videorecorder->expects($this->exactly(2))->method('resetIndex');

        $videorecorder->turnOn();
        $videorecorder->insertCassette('cassette1');
        $videorecorder->insertCassette('cassette2');
        $videorecorder->turnOff();
    }

    public function testHandleRequestRecordsRequestWhenModeIsNewRecords(): void
    {
        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response('200', [], 'example response');
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

    public function testHandleRequestThrowsExceptionWhenModeIsNone(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            "The request does not match a previously recorded request and the 'mode' is set to 'none'. "
            ."If you want to send the request anyway, make sure your 'mode' is set to 'new_episodes'."
        );

        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response('200', [], 'example response');
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

    public function testHandleRequestRecordsRequestWhenModeIsOnceAndCassetteIsNew(): void
    {
        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response('200', [], 'example response');
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

    public function testHandleRequestThrowsExceptionWhenModeIsOnceAndCassetteIsOld(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            "The request does not match a previously recorded request and the 'mode' is set to 'once'. "
            ."If you want to send the request anyway, make sure your 'mode' is set to 'new_episodes'."
        );

        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response('200', [], 'example response');
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

    protected function getClientMock(Request $request, Response $response): HttpClient
    {
        $client = $this->getMockBuilder(HttpClient::class)->setMethods(['send'])->getMock();
        $client
            ->expects($this->once())
            ->method('send')
            ->with($request)
            ->willReturn($response);

        return $client;
    }

    protected function getCassetteMock(Request $request, Response $response, string $mode = VCR::MODE_NEW_EPISODES, bool $isNew = false): Cassette
    {
        $cassette = $this->getMockBuilder(Cassette::class)
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


    public function testPlaybackOfIdenticalRequestsAndMatcher(): void {

        $request1 = new Request('GET', 'https://example.com', ['Request-Version' => '1']);
        $response1 = new Response('200', [], 'response');

        $request2 = new Request('GET', 'https://example.com', ['Request-Version' => '2']);
        $response2 = new Response('200', [], 'response 2');


        $client = new class([
             $response1,
             $response2
        ]) extends HttpClient {
            private int $index = 0;
            public function __construct(private array $sequence)
            {
            }

            public function send(Request $request): Response
            {
                return $this->sequence[$this->index++];
            }

        };

        $configuration = new Configuration();
        $configuration->enableLibraryHooks([]);
        $configuration->setMode('new_episodes');
        $configuration->enableRequestMatchers(['body']);

        $videorecorder = new class($configuration, $client, VCRFactory::getInstance()) extends Videorecorder {
            public function setCassette(Cassette $cassette): void
            {
                $this->cassette = $cassette;
            }
        };

        vfsStream::setup('test');
        $storage = new Yaml(vfsStream::url('test/'), 'json_test');
        $cassette = new Cassette('cassette_name', $configuration, $storage);
        $videorecorder->setCassette($cassette);

        $this->assertEquals($response1, $videorecorder->handleRequest($request1));
        $this->assertEquals($response2, $videorecorder->handleRequest($request2));

        $this->assertEquals($response1->toArray(), $cassette->playback($request1, 0)->toArray());
        $this->assertNotEquals($response1->toArray(), $cassette->playback($request2, 1)->toArray());
        $this->assertEquals($response2->toArray(), $cassette->playback($request2, 1)->toArray());
    }
}
