<?php

declare(strict_types=1);

namespace VCR\PHPUnit;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PHPUnit trait to simplify Symfony HttpClient testing with VCR.
 *
 * This trait automatically wraps Symfony HttpClient instances with VCRHttpClient,
 * making VCR integration transparent for tests.
 *
 * Usage:
 * ```php
 * class MyApiTest extends TestCase
 * {
 *     use SymfonyHttpClientTestTrait;
 *
 *     public function testApiCall(): void
 *     {
 *         $client = $this->createHttpClient(); // Automatically wrapped!
 *         $response = $client->request('GET', 'https://api.example.com/data');
 *         // ...
 *     }
 * }
 * ```
 */
trait SymfonyHttpClientTestTrait
{
    /**
     * Create a VCR-compatible Symfony HttpClient.
     *
     * @param string              $type    'curl' or 'native'
     * @param array<string,mixed> $options HttpClient options
     */
    protected function createHttpClient(string $type = 'curl', array $options = []): HttpClientInterface
    {
        // Use SymfonyHttpClientHook factory methods for consistent behavior
        return match ($type) {
            'native' => \VCR\LibraryHooks\SymfonyHttpClientHook::createNative($options),
            default => \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl($options),
        };
    }

    /**
     * Create a CurlHttpClient wrapped for VCR.
     *
     * This method creates a VCR-compatible Symfony CurlHttpClient.
     * The client is automatically wrapped to avoid Symfony's inflate issues.
     *
     * @param array<string,mixed> $options
     */
    protected function createCurlHttpClient(array $options = []): HttpClientInterface
    {
        return $this->createHttpClient('curl', $options);
    }

    /**
     * Create a NativeHttpClient wrapped for VCR.
     *
     * This method creates a VCR-compatible Symfony NativeHttpClient.
     * The client is automatically wrapped to avoid Symfony's inflate issues.
     *
     * @param array<string,mixed> $options
     */
    protected function createNativeHttpClient(array $options = []): HttpClientInterface
    {
        return $this->createHttpClient('native', $options);
    }
}
