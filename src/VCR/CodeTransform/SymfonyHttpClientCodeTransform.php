<?php

declare(strict_types=1);

namespace VCR\CodeTransform;

use VCR\Util\Assertion;

/**
 * Code transformation for Symfony HttpClient.
 *
 * Transforms instantiations of Symfony HttpClient classes to use VCR's wrapper:
 *   new CurlHttpClient() → \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl()
 *   new NativeHttpClient() → \VCR\LibraryHooks\SymfonyHttpClientHook::createNative()
 *   HttpClient::create() → \VCR\LibraryHooks\SymfonyHttpClientHook::create()
 */
class SymfonyHttpClientCodeTransform extends AbstractCodeTransform
{
    public const NAME = 'vcr_symfony_http_client';

    /**
     * @var array<string, string>
     */
    private static $patterns = [
        // Match: \Symfony\Component\HttpClient\HttpClient::create(...) - most specific first
        '/\b\\\?Symfony\\\Component\\\HttpClient\\\HttpClient::create\s*\(/i' => '\VCR\LibraryHooks\SymfonyHttpClientHook::create(',

        // Match: new \Symfony\Component\HttpClient\CurlHttpClient(...)
        '/\bnew\s+\\\?Symfony\\\Component\\\HttpClient\\\CurlHttpClient\s*\(/i' => '\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(',

        // Match: new \Symfony\Component\HttpClient\NativeHttpClient(...)
        '/\bnew\s+\\\?Symfony\\\Component\\\HttpClient\\\NativeHttpClient\s*\(/i' => '\VCR\LibraryHooks\SymfonyHttpClientHook::createNative(',

        // Match: HttpClient::create(...) - specifically Symfony's HttpClient
        '/\bHttpClient::create\s*\(/i' => '\VCR\LibraryHooks\SymfonyHttpClientHook::create(',

        // Match: new CurlHttpClient(...) but not: $this->new, class::new, etc.
        '/\bnew\s+CurlHttpClient\s*\(/i' => '\VCR\LibraryHooks\SymfonyHttpClientHook::createCurl(',

        // Match: new NativeHttpClient(...)
        '/\bnew\s+NativeHttpClient\s*\(/i' => '\VCR\LibraryHooks\SymfonyHttpClientHook::createNative(',
    ];

    protected function transformCode(string $code): string
    {
        $transformedCode = preg_replace(array_keys(self::$patterns), array_values(self::$patterns), $code);
        Assertion::string($transformedCode);

        return $transformedCode;
    }
}
