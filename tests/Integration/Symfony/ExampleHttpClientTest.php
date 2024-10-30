<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Tests example request.
 */
class ExampleHttpClientTest extends TestCase
{
    private const TEST_GET_URL = 'https://api.chew.pro/trbmb';
    private const TEST_POST_URL = 'https://httpbin.org/post';
    private const TEST_POST_BODY = '{"foo":"bar"}';

    /** @var string[] */
    protected $ignoreHeaders = [
        'Accept',
        'Connect-Time',
        'Total-Route-Time',
        'X-Request-Id',
    ];

    protected function setUp(): void
    {
        vfsStream::setup('testDir');
        \VCR\VCR::configure()->setCassettePath(vfsStream::url('testDir'));
    }

    public function testRequestGET(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $originalRequest = $this->requestGET();
        $this->assertValidGETResponse($originalRequest);
        $interceptedRequest = $this->requestGET();
        $this->assertValidGETResponse($interceptedRequest);
        self::assertEquals($originalRequest, $interceptedRequest);
        $repeatInterceptedRequest = $this->requestGET();
        self::assertEquals($interceptedRequest, $repeatInterceptedRequest);
        \VCR\VCR::turnOff();
    }

    public function testRequestPOST(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $originalRequest = $this->requestPOST();
        $this->assertValidPOSTResponse($originalRequest);
        $interceptedRequest = $this->requestPOST();
        $this->assertValidPOSTResponse($interceptedRequest);
        self::assertEquals($originalRequest, $interceptedRequest);
        $repeatInterceptedRequest = $this->requestPOST();
        self::assertEquals($interceptedRequest, $repeatInterceptedRequest);
        \VCR\VCR::turnOff();
    }

    /**
     * @return null[]|null
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function requestGET()
    {
        $exampleClient = new ExampleHttpClient();

        $response = $exampleClient->get(self::TEST_GET_URL);

        return $response;
    }

    /**
     * @return null[]|null
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function requestPOST()
    {
        $exampleClient = new ExampleHttpClient();

        $response = $exampleClient->post(self::TEST_POST_URL, self::TEST_POST_BODY);
        foreach ($this->ignoreHeaders as $header) {
            if (false === empty($response['headers'])) {
                unset($response['headers'][$header]);
            }
        }
        unset($response['origin']);

        return $response;
    }

    /**
     * @return null[]|null
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    protected function requestPOSTIntercepted()
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('test-cassette.yml');
        $info = $this->requestPOST();
        \VCR\VCR::turnOff();

        return $info;
    }

    protected function assertValidGETResponse(mixed $info): void
    {
        self::assertIsArray($info, 'Response is not an array.');
        self::assertArrayHasKey('0', $info, 'API did not return any value.');
    }

    protected function assertValidPOSTResponse(mixed $info): void
    {
        self::assertIsArray($info, 'Response is not an array.');
        self::assertArrayHasKey('url', $info, "Key 'url' not found.");
        self::assertEquals(self::TEST_POST_URL, $info['url'], "Value for key 'url' wrong.");
        self::assertArrayHasKey('headers', $info, "Key 'headers' not found.");
        self::assertIsArray($info['headers'], 'Headers is not an array.');
        self::assertEquals(self::TEST_POST_BODY, $info['data'], 'Correct request body was not sent.');
    }
}
