<?php

namespace VCR;

use lapistano\ProxyObject\ProxyBuilder;
use org\bovigo\vfs\vfsStream;

/**
 * Test Videorecorder.
 */
class VideorecorderTest extends \PHPUnit_Framework_TestCase
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
        $configuration->enableLibraryHooks(array());
        $videorecorder = $this->getMockBuilder('\VCR\Videorecorder')
            ->setConstructorArgs(array($configuration, new Util\HttpClient(), VCRFactory::getInstance()))
            ->setMethods(array('eject'))
            ->getMock();

        $videorecorder->expects($this->exactly(2))->method('eject');

        $videorecorder->turnOn();
        $videorecorder->insertCassette('cassette1');
        $videorecorder->insertCassette('cassette2');
        $videorecorder->turnOff();
    }

    public function testHandleRequestRecordsRequestWhenModeIsNewRecords()
    {
        $request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
        $response = new Response(200, array(), 'example response');
        $client = $this->getClientMock($request, $response);
        $configuration = new Configuration();
        $configuration->enableLibraryHooks(array());
        $configuration->setMode('new_episodes');

        $proxy = new ProxyBuilder('\VCR\Videorecorder');
        $videorecorder = $proxy
            ->setConstructorArgs(array($configuration, $client, VCRFactory::getInstance()))
            ->setProperties(array('cassette', 'client'))
            ->getProxy();
        $videorecorder->client = $client;
        $videorecorder->cassette = $this->getCassetteMock($request, $response);

        $this->assertEquals($response, $videorecorder->handleRequest($request));
    }

    public function testHandleRequestThrowsExceptionWhenModeIsNone()
    {
        $this->setExpectedException(
            'LogicException',
            "Invalid http request. The cassette inserted did not have the necessary response. "
            . "If you want to send a request anyway, make sure your mode is set to new_episodes.");

        $request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
        $response = new Response(200, array(), 'example response');
        $client = $this->getMockBuilder('\VCR\Util\HttpClient')->getMock();
        $configuration = new Configuration();
        $configuration->enableLibraryHooks(array());
        $configuration->setMode('none');

        $proxy = new ProxyBuilder('\VCR\Videorecorder');
        $videorecorder = $proxy
            ->setConstructorArgs(array($configuration, $client, VCRFactory::getInstance()))
            ->setProperties(array('cassette', 'client'))
            ->getProxy();
        $videorecorder->client = $client;

        $videorecorder->cassette = $this->getCassetteMock($request, $response, 'none');

        $videorecorder->handleRequest($request);
    }

    public function testHandleRequestRecordsRequestWhenModeIsOnceAndCassetteIsNew()
    {
        $request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
        $response = new Response(200, array(), 'example response');
        $client = $this->getClientMock($request, $response);
        $configuration = new Configuration();
        $configuration->enableLibraryHooks(array());
        $configuration->setMode('once');

        $proxy = new ProxyBuilder('\VCR\Videorecorder');
        $videorecorder = $proxy
            ->setConstructorArgs(array($configuration, $client, VCRFactory::getInstance()))
            ->setProperties(array('cassette', 'client'))
            ->getProxy();
        $videorecorder->client = $client;

        $videorecorder->cassette = $this->getCassetteMock($request, $response, 'once', true);

        $this->assertEquals($response, $videorecorder->handleRequest($request));
    }

    public function testHandleRequestThrowsExceptionWhenModeIsOnceAndCassetteIsOld()
    {
        $this->setExpectedException(
            'LogicException',
            "Invalid http request. The cassette inserted did not have the necessary response. "
            . "If you want to send a request anyway, make sure your mode is set to new_episodes.");

        $request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
        $response = new Response(200, array(), 'example response');
        $client = $this->getMockBuilder('\VCR\Util\HttpClient')->getMock();
        $configuration = new Configuration();
        $configuration->enableLibraryHooks(array());
        $configuration->setMode('once');

        $proxy = new ProxyBuilder('\VCR\Videorecorder');
        $videorecorder = $proxy
            ->setConstructorArgs(array($configuration, $client, VCRFactory::getInstance()))
            ->setProperties(array('cassette', 'client'))
            ->getProxy();
        $videorecorder->client = $client;

        $videorecorder->cassette = $this->getCassetteMock($request, $response, 'once', false);

        $videorecorder->handleRequest($request);
    }

    protected function getClientMock($request, $response)
    {
        $client = $this->getMockBuilder('\VCR\Util\HttpClient')->setMethods(array('send'))->getMock();
        $client
            ->expects($this->once())
            ->method('send')
            ->with($request)
            ->will($this->returnValue($response));

        return $client;
    }

    protected function getCassetteMock($request, $response, $mode = 'new_episodes', $isNew = false)
    {
        $cassette = $this->getMockBuilder('\VCR\Cassette')
            ->disableOriginalConstructor()
            ->setMethods(array('hasResponse', 'record', 'playback', 'isNew'))
            ->getMock();
        $cassette
            ->expects($this->once())
            ->method('hasResponse')
            ->with($request)
            ->will($this->returnValue(false));

        if ($mode == 'new_episodes' || $mode == 'once' && $isNew === true) {
            $cassette
                ->expects($this->once())
                ->method('record')
                ->with($request, $response);
        }

        if ($mode == 'once') {
            $cassette
                ->expects($this->once())
                ->method('isNew')
                ->will($this->returnValue($isNew));
        }

        return $cassette;
    }
}
