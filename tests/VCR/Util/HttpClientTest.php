<?php

namespace VCR\Util;

use PHPUnit\Framework\TestCase;
use VCR\Response;
use VCR\Request;

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
