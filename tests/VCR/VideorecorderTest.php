<?php

namespace VCR;

use lapistano\ProxyObject\ProxyBuilder;

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
        $videorecorder = $this->getMockBuilder('\VCR\Videorecorder')
            ->setConstructorArgs(array(new Configuration(), new Util\HttpClient(), VCRFactory::getInstance()))
            ->setMethods(array('eject'))
            ->getMock();

        $videorecorder->expects($this->exactly(2))->method('eject');

        $videorecorder->turnOn();
        $videorecorder->insertCassette('cassette1');
        $videorecorder->insertCassette('cassette2');
        $videorecorder->turnOff();
    }

    public function testHandleRequestRecordsRequest()
    {
        $request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
        $response = new Response(200, array(), 'example response');
        $client = $this->getClientMock($request, $response);
        $configuration = new Configuration();
        $configuration->enableLibraryHooks(array());

        $proxy = new ProxyBuilder('\VCR\Videorecorder');
        $videorecorder = $proxy
            ->setConstructorArgs(array($configuration, $client, VCRFactory::getInstance()))
            ->setProperties(array('cassette', 'client'))
            ->getProxy();
        $videorecorder->client = $client;
        $videorecorder->cassette = $this->getCassetteMock($request, $response);

        $this->assertEquals($response, $videorecorder->handleRequest($request));
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

    protected function getCassetteMock($request, $response)
    {
        $cassette = $this->getMockBuilder('\VCR\Cassette')
            ->disableOriginalConstructor()
            ->setMethods(array('hasResponse', 'record', 'playback'))
            ->getMock();
        $cassette
            ->expects($this->once())
            ->method('hasResponse')
            ->with($request)
            ->will($this->returnValue(false));
        $cassette
            ->expects($this->once())
            ->method('record')
            ->with($request, $response);
        $cassette
            ->expects($this->once())
            ->method('playback')
            ->with($request)
            ->will($this->returnValue($response));

        return $cassette;
    }
}
