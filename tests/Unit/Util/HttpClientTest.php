<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use VCR\Request;
use VCR\Util\CurlException;
use VCR\Util\HttpClient;

final class HttpClientTest extends TestCase
{
    public function testHttpClientOnError(): void
    {
        $httpClient = new HttpClient();
        // Request on a closed port
        $request = new Request('GET', 'http://localhost:9934');

        $this->expectException(CurlException::class);
        $httpClient->send($request);
    }
}
