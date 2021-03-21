<?php

namespace VCR;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Blackhole;

/**
 * Test private data scrubbing and replacement.
 */
class ScrubbingTest extends TestCase
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Storage\Storage
     */
    protected $storage;

    protected function setUp(): void
    {
        $this->request = new Request('GET', 'http://example.com?secret=query_secret', [
            'X-Req-Header' => 'secret;request_header_secret',
        ]);
        $this->request->setBody('This is a request_body_secret');
        $this->request->setPostFields([
            'password' => 'post_field_secret',
        ]);
        $this->response = new Response('200', [
            'X-Resp-Header' => 'secret;response_header_secret',
        ], 'This is a response_body_secret');
        $this->storage = new class() extends Blackhole {
            /**
             * @var array<string,mixed>
             */
            public $recording;

            public function storeRecording(array $recording): void
            {
                $this->recording = $recording;
            }
        };
    }

    public function testItStillReturnsRecording(): void
    {
        $config = new Configuration();
        $config->addRedaction('<SECRET1>', 'response_body_secret');
        $cassette = new Cassette('test', $config, $this->storage);
        $cassette->record($this->request, $this->response);

        $this->assertArrayHasKey('request', $this->storedRecording());
        $this->assertArrayHasKey('response', $this->storedRecording());
    }

    public function testItScrubsInRequest(): void
    {
        $config = new Configuration();
        $config->addRedaction('<REQ_QUERY_SECRET>', 'query_secret')
            ->addRedaction('<REQ_HEADER_SECRET>', 'request_header_secret')
            ->addRedaction('<REQ_BODY_SECRET>', 'request_body_secret')
            ->addRedaction('<REQ_FIELD_SECRET>', 'post_field_secret');

        $cassette = new Cassette('test', $config, $this->storage);
        $cassette->record($this->request, $this->response);
        $requestPart = $this->storedRecording()['request'];

        $this->assertEquals('http://example.com?secret=<REQ_QUERY_SECRET>', $requestPart['url']);
        $this->assertContains('X-Req-Header: secret;<REQ_HEADER_SECRET>', $requestPart['headers']);
        $this->assertEquals('This is a <REQ_BODY_SECRET>', $requestPart['body']);
        $this->assertEquals('<REQ_FIELD_SECRET>', $requestPart['post_fields']['password']);
    }

    public function testItReplacesMultipleInOneField(): void
    {
        $config = new Configuration();
        $config->addRedaction('<SECRET1>', 'password is passw0rd')
            ->addRedaction('<SECRET2>', 'pin is 1234');
        $this->request->setBody('Your password is passw0rd and your pin is 1234.');

        $cassette = new Cassette('test', $config, $this->storage);
        $cassette->record($this->request, $this->response);
        $requestPart = $this->storedRecording()['request'];
        $this->assertEquals('Your <SECRET1> and your <SECRET2>.', $requestPart['body']);
    }

    public function testScrubsInResponse(): void
    {
        $config = new Configuration();
        $config->addRedaction('<RESP_BODY_SECRET>', 'response_body_secret')
            ->addRedaction('<RESP_HEADER_SECRET>', 'response_header_secret');
        $cassette = new Cassette('test', $config, $this->storage);
        $cassette->record($this->request, $this->response);
        $responsePart = $this->storedRecording()['response'];

        $this->assertContains('X-Resp-Header: secret;<RESP_HEADER_SECRET>', $responsePart['headers']);
        $this->assertEquals('This is a <RESP_BODY_SECRET>', $responsePart['body']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDynamicFilterReturningFalseyWorks(): void
    {
        $config = new Configuration();
        $config->addRedaction('<DYNAMIC_SECRET>', function (Request $request, Response $response) {
            return null;
        });
        $cassette = new Cassette('test', $config, $this->storage);
        $cassette->record($this->request, $this->response);
    }

    public function testDynamicFilterReturningTruthyIsScrubbed(): void
    {
        $config = new Configuration();
        $config->addRedaction('<DYNAMIC_SECRET>', function (Request $request, Response $response) {
            return 'This is a response_body_secret';
        });
        $cassette = new Cassette('test', $config, $this->storage);
        $cassette->record($this->request, $this->response);
        $responsePart = $this->storedRecording()['response'];

        $this->assertEquals('<DYNAMIC_SECRET>', $responsePart['body']);
    }

    public function testWorksWithNoMatchingFilters(): void
    {
        $config = new Configuration();
        $config->enableRequestMatchers(['url']);
        $cassette = new Cassette('scrubbing_test', $config, new Storage\Yaml('tests/fixtures', 'scrubbing_test'));

        $response = $cassette->playback($this->request);

        $this->assertEquals('This is a scrubbed test dummy.', $response->getBody());
        $this->assertEquals('"359670651"', $response->getHeader('Etag'));
    }

    public function testUnscrubsResponseFields(): void
    {
        $config = new Configuration();
        $config->enableRequestMatchers(['url'])
            ->addRedaction('359670651', 'RESP_HEADER_SECRET')
            ->addRedaction('scrubbed test dummy', 'RESP_BODY_SECRET');
        $cassette = new Cassette('scrubbing_test', $config, new Storage\Yaml('tests/fixtures', 'scrubbing_test'));

        $response = $cassette->playback($this->request);

        $this->assertEquals('This is a RESP_BODY_SECRET.', $response->getBody());
        $this->assertEquals('"RESP_HEADER_SECRET"', $response->getHeader('Etag'));
    }

    public function testMatchesRequestsAfterUnscrubbing(): void
    {
        $config = new Configuration();
        $config->enableRequestMatchers(['url'])
            ->addRedaction('query_secret', 'secret123')
            ->addRedaction('scrubbed test dummy', 'RESP_BODY_SECRET');
        $cassette = new Cassette('scrubbing_test', $config, new Storage\Yaml('tests/fixtures', 'scrubbing_test'));

        $this->request->setUrl('http://example.com?secret=secret123');
        $response = $cassette->playback($this->request);

        $this->assertNotNull($response);
        $this->assertEquals('This is a RESP_BODY_SECRET.', $response->getBody());
    }

    /**
     * @return array<string,mixed> The recording from the anonymous test Storage
     */
    private function storedRecording(): array
    {
        /* @phpstan-ignore-next-line */
        return $this->storage->recording;
    }
}
