<?php

namespace VCR\Util;

use VCR\Response;
use VCR\Request;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    public function testCreateHttpClient()
    {
        $this->assertInstanceOf('\VCR\Util\HttpClient', new HttpClient());
    }

    public function testCreateHttpClientWithMock()
    {
        $this->assertInstanceOf('\VCR\Util\HttpClient', new HttpClient());
    }
}
