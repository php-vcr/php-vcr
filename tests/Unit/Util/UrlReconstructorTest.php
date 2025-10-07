<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use VCR\Util\UrlReconstructor;

class UrlReconstructorTest extends TestCase
{
    public function testReconstructFromHostHeaderWhenHostDiffers(): void
    {
        $url = 'http://188.114.97.2/posts/1';
        $hostHeader = 'jsonplaceholder.typicode.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://jsonplaceholder.typicode.com/posts/1', $result);
    }

    public function testReconstructFromHostHeaderWithHttps(): void
    {
        $url = 'https://1.2.3.4/api/users';
        $hostHeader = 'api.example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('https://api.example.com/api/users', $result);
    }

    public function testReconstructFromHostHeaderWithNonStandardPort(): void
    {
        $url = 'http://127.0.0.1:8080/test';
        $hostHeader = 'localhost';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://localhost:8080/test', $result);
    }

    public function testReconstructFromHostHeaderWithQueryString(): void
    {
        $url = 'http://10.0.0.1/search?q=test&limit=10';
        $hostHeader = 'search.example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://search.example.com/search?q=test&limit=10', $result);
    }

    public function testReconstructFromHostHeaderWithFragment(): void
    {
        $url = 'http://192.168.1.1/page#section';
        $hostHeader = 'docs.example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://docs.example.com/page#section', $result);
    }

    public function testReconstructFromHostHeaderWithQueryAndFragment(): void
    {
        $url = 'https://8.8.8.8/docs?lang=en#intro';
        $hostHeader = 'help.example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('https://help.example.com/docs?lang=en#intro', $result);
    }

    public function testReconstructFromHostHeaderIgnoresStandardHttpPort80(): void
    {
        $url = 'http://1.2.3.4:80/api';
        $hostHeader = 'api.example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://api.example.com/api', $result);
    }

    public function testReconstructFromHostHeaderIgnoresStandardHttpsPort443(): void
    {
        $url = 'https://1.2.3.4:443/secure';
        $hostHeader = 'secure.example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('https://secure.example.com/secure', $result);
    }

    public function testReconstructFromHostHeaderWithRootPath(): void
    {
        $url = 'http://127.0.0.1/';
        $hostHeader = 'example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://example.com/', $result);
    }

    public function testReconstructFromHostHeaderWithNoPath(): void
    {
        $url = 'http://10.0.0.1';
        $hostHeader = 'example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://example.com/', $result);
    }

    public function testReconstructReturnsNullWhenHostMatches(): void
    {
        $url = 'http://example.com/api';
        $hostHeader = 'example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertNull($result, 'Should return null when no reconstruction is needed');
    }

    public function testReconstructReturnsNullWhenUrlHasNoHost(): void
    {
        $url = '/relative/path';
        $hostHeader = 'example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertNull($result, 'Should return null for URLs without host');
    }

    public function testReconstructReturnsNullForInvalidUrl(): void
    {
        $url = 'not a valid url';
        $hostHeader = 'example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertNull($result);
    }

    public function testReconstructHandlesIPv6Address(): void
    {
        $url = 'http://[2001:db8::1]/api';
        $hostHeader = 'api.example.com';

        $result = UrlReconstructor::reconstructFromHostHeader($url, $hostHeader);

        $this->assertSame('http://api.example.com/api', $result);
    }
}
