<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Sequential multi-request record/replay for Symfony NativeHttpClient.
 * Record/replay skipped — NativeHttpClient sends headers as array. See #329.
 * Cassette name prefixed 'symfony-native-seq-'.
 */
final class SequentialRequestsTest extends AbstractHttpServerIntegrationTestCase
{
    public function testSequentialRequestsRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $countBefore = $this->server()->getRequestCount();
        $client = new NativeHttpClient();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-seq.yml');
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
        \VCR\VCR::insertCassette('symfony-native-seq.yml');
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
