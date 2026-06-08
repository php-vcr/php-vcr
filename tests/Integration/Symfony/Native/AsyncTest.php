<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Concurrent lazy requests for Symfony NativeHttpClient.
 * Record/replay skipped — NativeHttpClient sends headers as array. See #329.
 * Cassette name prefixed 'symfony-native-async-'.
 */
final class AsyncTest extends AbstractHttpServerIntegrationTestCase
{
    public function testConcurrentRequestsRecordAndReplay(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        $countBefore = $this->server()->getRequestCount();
        $client = new NativeHttpClient();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-async.yml');
        $r1 = $client->request('GET', self::$baseUrl.'/get');
        $r2 = $client->request('GET', self::$baseUrl.'/get?foo=42');
        $s1 = $r1->getStatusCode();
        $s2 = $r2->getStatusCode();
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 2, $countAfterRecord, 'Both requests must hit the server during recording');
        $this->assertSame(200, $s1);
        $this->assertSame(200, $s2);

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-async.yml');
        $t1 = $client->request('GET', self::$baseUrl.'/get');
        $t2 = $client->request('GET', self::$baseUrl.'/get?foo=42');
        $u1 = $t1->getStatusCode();
        $u2 = $t2->getStatusCode();
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $u1);
        $this->assertSame(200, $u2);
    }
}
