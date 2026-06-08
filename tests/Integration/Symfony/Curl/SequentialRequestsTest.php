<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use Symfony\Component\HttpClient\CurlHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Sequential multi-request record/replay for Symfony CurlHttpClient.
 * Record/replay skipped — same curl_getinfo limitation as #329.
 * Cassette name prefixed 'symfony-curl-seq-'.
 */
final class SequentialRequestsTest extends AbstractHttpServerIntegrationTestCase
{
    public function testSequentialRequestsRecordAndReplay(): void
    {
        $this->markTestSkipped('CurlHttpClient: curl_getinfo() before curl_multi_exec. See #329.');

        $countBefore = $this->server()->getRequestCount();
        $client = new CurlHttpClient();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-seq.yml');
        $s1 = $client->request('GET', self::$baseUrl.'/get')->getStatusCode();
        $s2 = $client->request('GET', self::$baseUrl.'/get?page=2')->getStatusCode();
        $s3 = $client->request('GET', self::$baseUrl.'/get?page=3')->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 3, $countAfterRecord, 'All three requests must hit the server');
        $this->assertSame(200, $s1);
        $this->assertSame(200, $s2);
        $this->assertSame(200, $s3);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-seq.yml');
        $t1 = $client->request('GET', self::$baseUrl.'/get')->getStatusCode();
        $t2 = $client->request('GET', self::$baseUrl.'/get?page=2')->getStatusCode();
        $t3 = $client->request('GET', self::$baseUrl.'/get?page=3')->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $t1);
        $this->assertSame(200, $t2);
        $this->assertSame(200, $t3);
    }
}
