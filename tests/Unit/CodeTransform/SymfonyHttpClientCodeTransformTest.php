<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\CodeTransform;

use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\SymfonyHttpClientCodeTransform;

final class SymfonyHttpClientCodeTransformTest extends TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode(string $expected, string $code): void
    {
        $codeTransform = new class extends SymfonyHttpClientCodeTransform {
            // A proxy to access the protected transformCode method.
            public function publicTransformCode(string $code): string
            {
                return $this->transformCode($code);
            }
        };

        $this->assertEquals($expected, $codeTransform->publicTransformCode($code));
    }

    /** @return array<string[]> */
    public function codeSnippetProvider(): array
    {
        return [
            // CurlHttpClient transformations
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(',
                'new CurlHttpClient(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(',
                'new CURLHTTPCLIENT(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(',
                'new \Symfony\Component\HttpClient\CurlHttpClient(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(',
                'new Symfony\Component\HttpClient\CurlHttpClient(',
            ],

            // NativeHttpClient transformations
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createNative(',
                'new NativeHttpClient(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createNative(',
                'new NATIVEHTTPCLIENT(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createNative(',
                'new \Symfony\Component\HttpClient\NativeHttpClient(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createNative(',
                'new Symfony\Component\HttpClient\NativeHttpClient(',
            ],

            // HttpClient::create() transformations
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::create(',
                'HttpClient::create(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::create(',
                'HTTPCLIENT::CREATE(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::create(',
                '\Symfony\Component\HttpClient\HttpClient::create(',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::create(',
                'Symfony\Component\HttpClient\HttpClient::create(',
            ],
            [
                '$client = \VCR\LibraryHooks\SymfonyHttpClientHook::create();',
                '$client = HttpClient::create();',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::create([\'timeout\' => 30])',
                'HttpClient::create([\'timeout\' => 30])',
            ],

            // Should NOT transform method calls
            [
                '$client->new CurlHttpClient(',
                '$client->new CurlHttpClient(',
            ],
            [
                'SomeClass::new CurlHttpClient(',
                'SomeClass::new CurlHttpClient(',
            ],

            // Should NOT transform if not followed by opening parenthesis
            [
                'new CurlHttpClient',
                'new CurlHttpClient',
            ],

            // Multiple transformations in same code
            [
                '$curl = \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(); $native = \VCR\LibraryHooks\SymfonyHttpClientHook::createNative();',
                '$curl = new CurlHttpClient(); $native = new NativeHttpClient();',
            ],

            // With options
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl([\'timeout\' => 30])',
                'new CurlHttpClient([\'timeout\' => 30])',
            ],
            [
                '\VCR\LibraryHooks\SymfonyHttpClientHook::createNative([\'verify_peer\' => false])',
                'new NativeHttpClient([\'verify_peer\' => false])',
            ],

            // Real world usage patterns
            [
                '$client = \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl();',
                '$client = new CurlHttpClient();',
            ],
            [
                '$httpClient = \VCR\LibraryHooks\SymfonyHttpClientHook::createNative([\'timeout\' => 30, \'verify_peer\' => false]);',
                '$httpClient = new NativeHttpClient([\'timeout\' => 30, \'verify_peer\' => false]);',
            ],

            // In class instantiation
            [
                'return \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl();',
                'return new CurlHttpClient();',
            ],
            [
                '$this->client = \VCR\LibraryHooks\SymfonyHttpClientHook::createNative($options);',
                '$this->client = new NativeHttpClient($options);',
            ],

            // Complex scenarios
            [
                '$curl = \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(); $traceable = new TraceableHttpClient($curl);',
                '$curl = new CurlHttpClient(); $traceable = new TraceableHttpClient($curl);',
            ],

            // Should transform only the target clients, not other classes
            [
                'new HttpClient(); \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(); new SomeOtherClient();',
                'new HttpClient(); new CurlHttpClient(); new SomeOtherClient();',
            ],
        ];
    }

    public function testGetName(): void
    {
        $transform = new SymfonyHttpClientCodeTransform();
        $this->assertEquals('vcr_symfony_http_client', SymfonyHttpClientCodeTransform::NAME);
    }

    public function testTransformPreservesCodeStructure(): void
    {
        $codeTransform = new class extends SymfonyHttpClientCodeTransform {
            public function publicTransformCode(string $code): string
            {
                return $this->transformCode($code);
            }
        };

        $code = <<<'PHP'
            <?php
            namespace App;

            class MyHttpClient
            {
                public function __construct()
                {
                    $this->client = new CurlHttpClient([
                        'timeout' => 30,
                        'verify_peer' => false,
                    ]);
                }
            }
            PHP;

        $transformed = $codeTransform->publicTransformCode($code);

        // Should contain the transformed call
        $this->assertStringContainsString('\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(', $transformed);
        // Should preserve the rest of the code structure
        $this->assertStringContainsString('namespace App;', $transformed);
        $this->assertStringContainsString('class MyHttpClient', $transformed);
    }
}
