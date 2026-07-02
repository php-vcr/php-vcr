<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Cassette;
use VCR\Configuration;
use VCR\Request;
use VCR\Response;
use VCR\Storage\Storage;
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
            ->onlyMethods(['eject', 'resetIndex'])
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

    public function testInsertCassettePurgesStorageWhenModeIsAll(): void
    {
        vfsStream::setup('testDir');
        $cassetteName = 'purge_test';
        vfsStream::create([$cassetteName => "- request:\n    url: http://example.com\n  response:\n    status: 200\n"]);

        $factory = VCRFactory::getInstance();
        $configuration = $factory->get('VCR\Configuration');
        $configuration->setCassettePath(vfsStream::url('testDir'));
        $configuration->enableLibraryHooks([]);
        $configuration->setMode(VCR::MODE_ALL);

        $videorecorder = new Videorecorder($configuration, new HttpClient(), $factory);
        $videorecorder->turnOn();
        $videorecorder->insertCassette($cassetteName);

        $content = (string) file_get_contents(vfsStream::url('testDir').'/'.$cassetteName);
        $this->assertSame('', $content);

        $videorecorder->turnOff();
    }

    public function testInsertCassetteThrowsExceptionWhenStorageIsNotPurgeableInModeAll(): void
    {
        $nonPurgeableStorage = new class implements Storage {
            public function storeRecording(array $recording): void {}

            public function isNew(): bool { return true; }

            public function current(): ?array { return null; }

            public function key(): int { return 0; }

            public function next(): void {}

            public function rewind(): void {}

            public function valid(): bool { return false; }
        };

        $configuration = new Configuration();
        $configuration->enableLibraryHooks([]);
        $configuration->setMode(VCR::MODE_ALL);

        $videorecorder = new class($configuration, new HttpClient(), VCRFactory::getInstance(), $nonPurgeableStorage) extends Videorecorder {
            private Storage $nonPurgeableStorage;

            public function __construct(Configuration $config, HttpClient $client, VCRFactory $factory, Storage $storage)
            {
                parent::__construct($config, $client, $factory);
                $this->nonPurgeableStorage = $storage;
            }

            protected function createStorage(string $cassetteName): Storage
            {
                return $this->nonPurgeableStorage;
            }
        };

        $videorecorder->turnOn();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/does not support MODE_ALL/');
        $videorecorder->insertCassette('cassette');
    }

    public function testHandleRequestSkipsPlaybackAndAlwaysRecordsWhenModeIsAll(): void
    {
        $request = new Request('GET', 'http://example.com', ['User-Agent' => 'Unit-Test']);
        $response = new Response('200', [], 'example response');
        $client = $this->getClientMock($request, $response);
        $configuration = new Configuration();
        $configuration->enableLibraryHooks([]);
        $configuration->setMode(VCR::MODE_ALL);

        $cassette = $this->getMockBuilder(Cassette::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['record', 'playback', 'isNew', 'getName'])
            ->getMock();
        $cassette->expects($this->never())->method('playback');
        $cassette->expects($this->once())->method('record')->with($request, $response);
        $cassette->method('getName')->willReturn('foobar');

        $videorecorder = new class($configuration, $client, VCRFactory::getInstance()) extends Videorecorder {
            public function setCassette(Cassette $cassette): void
            {
                $this->cassette = $cassette;
            }
        };

        $videorecorder->setCassette($cassette);

        $this->assertEquals($response, $videorecorder->handleRequest($request));
    }

    protected function getClientMock(Request $request, Response $response): HttpClient
    {
        $client = $this->getMockBuilder(HttpClient::class)->onlyMethods(['send'])->getMock();
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
            ->onlyMethods(['record', 'playback', 'isNew', 'getName'])
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
