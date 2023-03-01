<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use Assert\Assertion;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Event\Event;
use VCR\VCR;
use VCR\VCREvents;

final class VCRTest extends TestCase
{
    /** @var array<string, Event> */
    private $events;

    public static function setupBeforeClass(): void
    {
        VCR::configure()->setCassettePath('tests/fixtures');
    }

    public function testUseStaticCallsNotInitialized(): void
    {
        VCR::configure()->enableLibraryHooks(['stream_wrapper']);
        $this->expectException(\VCR\VCRException::class);
        $this->expectExceptionMessage('Please turn on VCR before inserting a cassette, use: VCR::turnOn()');
        VCR::insertCassette('some_name');
    }

    public function testShouldInterceptStreamWrapper(): void
    {
        VCR::configure()->enableLibraryHooks(['stream_wrapper']);
        VCR::turnOn();
        VCR::insertCassette('unittest_streamwrapper_test');
        $result = file_get_contents('http://example.com');
        $this->assertEquals('This is a stream wrapper test dummy.', $result, 'Stream wrapper call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    public function testShouldInterceptCurlLibrary(): void
    {
        VCR::configure()->enableLibraryHooks(['curl']);
        VCR::turnOn();
        VCR::insertCassette('unittest_curl_test');

        $output = $this->doCurlGetRequest('http://google.com/');

        $this->assertEquals('This is a curl test dummy.', $output, 'Curl call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    private function doCurlGetRequest(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, \CURLOPT_URL, $url);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, \CURLOPT_POST, false);
        $output = curl_exec($ch);
        curl_close($ch);

        Assertion::string($output);

        return $output;
    }

    public function testShouldInterceptSoapLibrary(): void
    {
        VCR::configure()->enableLibraryHooks(['soap']);
        VCR::turnOn();
        VCR::insertCassette('unittest_soap_test');

        $client = new \SoapClient('https://raw.githubusercontent.com/php-vcr/php-vcr/master/tests/fixtures/soap/wsdl/weather.wsdl', ['soap_version' => \SOAP_1_2]);
        $actual = $client->GetCityWeatherByZIP(['ZIP' => '10013']);
        $temperature = $actual->GetCityWeatherByZIPResult->Temperature;

        $this->assertEquals('1337', $temperature, 'Soap call was not intercepted.');
        VCR::eject();
        VCR::turnOff();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testShouldNotInterceptCallsToDevUrandom(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('/dev/urandom is not supported on Windows');
        }

        VCR::configure()->enableLibraryHooks(['stream_wrapper']);
        VCR::turnOn();
        VCR::insertCassette('unittest_urandom_test');

        // Just trying to open this will cause an exception if you're using is_file to filter
        // which paths to intercept.
        $output = file_get_contents('/dev/urandom', false, null, 0, 16);

        VCR::eject();
        VCR::turnOff();
    }

    public function testShouldThrowExceptionIfNoCassettePresent(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Invalid http request. No cassette inserted. Please make sure to insert '
            ."a cassette in your unit test using VCR::insertCassette('name');"
        );

        VCR::configure()->enableLibraryHooks(['stream_wrapper']);
        VCR::turnOn();
        // If there is no cassette inserted, a request should throw an exception
        file_get_contents('http://example.com'); // @phpstan-ignore-line
        VCR::turnOff();
    }

    public function testDoesNotBlockThrowingExceptions(): void
    {
        $this->configureVirtualCassette();

        VCR::turnOn();
        $this->expectException('InvalidArgumentException');
        VCR::insertCassette('unittest_cassette1');
        throw new \InvalidArgumentException('test');
    }

    private function configureVirtualCassette(): void
    {
        vfsStream::setup('testDir');
        VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testShouldSetAConfiguration(): void
    {
        VCR::configure()->setCassettePath('tests');
        VCR::turnOn();
        $this->assertEquals('tests', VCR::configure()->getCassettePath());
        VCR::turnOff();
    }

    public function testShouldDispatchBeforeAndAfterPlaybackWhenCassetteHasResponse(): void
    {
        VCR::configure()
            ->enableLibraryHooks(['curl']);
        $this->recordAllEvents();
        VCR::turnOn();
        VCR::insertCassette('unittest_curl_test');

        $this->doCurlGetRequest('http://google.com/');

        $this->assertEquals(
            [VCREvents::VCR_BEFORE_PLAYBACK, VCREvents::VCR_AFTER_PLAYBACK],
            $this->getRecordedEventNames()
        );
        VCR::eject();
        VCR::turnOff();
    }

    public function testShouldDispatchBeforeAfterHttpRequestAndBeforeRecordWhenCassetteHasNoResponse(): void
    {
        vfsStream::setup('testDir');
        VCR::configure()
            ->setCassettePath(vfsStream::url('testDir'))
            ->enableLibraryHooks(['curl']);
        $this->recordAllEvents();
        VCR::turnOn();
        VCR::insertCassette('virtual_cassette');

        $this->doCurlGetRequest('http://google.com/');

        $this->assertEquals(
            [
                VCREvents::VCR_BEFORE_PLAYBACK,
                VCREvents::VCR_BEFORE_HTTP_REQUEST,
                VCREvents::VCR_AFTER_HTTP_REQUEST,
                VCREvents::VCR_BEFORE_RECORD,
            ],
            $this->getRecordedEventNames()
        );
        VCR::eject();
        VCR::turnOff();
    }

    public function testFinfoWorksCorrectly(): void
    {
        $fileinfo = new \finfo(\FILEINFO_MIME_TYPE);

        $this->assertEquals(
            'text/plain',
            $fileinfo->file(__DIR__.'/../../.gitignore')
        );
    }

    private function recordAllEvents(): void
    {
        $allEventsToListen = [
            VCREvents::VCR_BEFORE_PLAYBACK,
            VCREvents::VCR_AFTER_PLAYBACK,
            VCREvents::VCR_BEFORE_HTTP_REQUEST,
            VCREvents::VCR_AFTER_HTTP_REQUEST,
            VCREvents::VCR_BEFORE_RECORD,
        ];
        foreach ($allEventsToListen as $eventToListen) {
            VCR::getEventDispatcher()->addListener($eventToListen, [$this, 'recordEvent']);
        }
    }

    public function recordEvent(Event $event, string $eventName): void
    {
        $this->events[$eventName] = $event;
    }

    /** @return string[] */
    private function getRecordedEventNames(): array
    {
        return array_keys($this->events);
    }
}
