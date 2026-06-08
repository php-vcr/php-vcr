<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Guzzle;

use GuzzleHttp\Client;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;

/**
 * Three sequential requests in one cassette — regression for issue #432
 * (stale curl handle state between sequential requests).
 * Cassette prefix 'guzzle-seq-' avoids VCRFactory cache collisions.
 */
final class SequentialRequestsTest extends AbstractHttpServerIntegrationTestCase
{
    public function testSequentialRequestsRecordAndReplay(): void
    {
        $countBefore = $this->server()->getRequestCount();
        $client = new Client();

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-seq.yml');
        $r1 = $client->get(self::$baseUrl.'/get');
        $r2 = $client->get(self::$baseUrl.'/get?page=2');
        $r3 = $client->get(self::$baseUrl.'/get?page=3');
        \VCR\VCR::turnOff();

        $countAfterRecord = $this->server()->getRequestCount();
        $this->assertSame($countBefore + 3, $countAfterRecord, 'All three requests must hit the server');
        $this->assertSame(200, $r1->getStatusCode());
        $this->assertSame(200, $r2->getStatusCode());
        $this->assertSame(200, $r3->getStatusCode());

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('guzzle-seq.yml');
        $s1 = $client->get(self::$baseUrl.'/get');
        $s2 = $client->get(self::$baseUrl.'/get?page=2');
        $s3 = $client->get(self::$baseUrl.'/get?page=3');
        \VCR\VCR::turnOff();

        $this->assertSame($countAfterRecord, $this->server()->getRequestCount(), 'Replay must not hit the server');
        $this->assertSame(200, $s1->getStatusCode());
        $this->assertSame(200, $s2->getStatusCode());
        $this->assertSame(200, $s3->getStatusCode());
    }
}
