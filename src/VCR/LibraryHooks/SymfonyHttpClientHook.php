<?php

declare(strict_types=1);

namespace VCR\LibraryHooks;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Util\StreamProcessor;
use VCR\VCRHttpClient;

/**
 * Hook for Symfony HttpClient.
 *
 * This hook uses code transformation to automatically wrap Symfony HttpClient
 * instances with VCRHttpClient, avoiding timing issues with gzip decompression.
 *
 * When enabled, it transforms:
 *   new CurlHttpClient() → \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl()
 */
class SymfonyHttpClientHook implements LibraryHook
{
    protected static string $status = self::DISABLED;
    protected static ?\Closure $requestCallback = null;

    /**
     * @var array<int, VCRHttpClient> Wrapped clients
     */
    protected static array $wrappedClients = [];

    public function __construct(
        private AbstractCodeTransform $codeTransformer,
        private StreamProcessor $processor
    ) {
    }

    public function enable(\Closure $requestCallback): void
    {
        if (self::ENABLED == static::$status) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        self::$requestCallback = $requestCallback;
        static::$status = self::ENABLED;
    }

    public function disable(): void
    {
        self::$requestCallback = null;
        self::$wrappedClients = [];
        static::$status = self::DISABLED;
    }

    public function isEnabled(): bool
    {
        return self::ENABLED == self::$status;
    }

    /**
     * Get the request callback.
     */
    public static function getRequestCallback(): ?\Closure
    {
        return self::$requestCallback;
    }

    /**
     * Create a VCR-compatible CurlHttpClient.
     *
     * This is a convenience method that automatically wraps the client
     * with VCRHttpClient to avoid Symfony's inflate issues.
     *
     * Usage in tests:
     *   $client = \VCR\LibraryHooks\SymfonyHttpClientHook::createCurl();
     *
     * @param array<string, mixed> $options HttpClient options
     */
    public static function createCurl(array $options = []): VCRHttpClient
    {
        return new VCRHttpClient(new CurlHttpClient($options));
    }

    /**
     * Create a VCR-compatible NativeHttpClient.
     *
     * This is a convenience method that automatically wraps the client
     * with VCRHttpClient to avoid Symfony's inflate issues.
     *
     * Usage in tests:
     *   $client = \VCR\LibraryHooks\SymfonyHttpClientHook::createNative();
     *
     * @param array<string, mixed> $options HttpClient options
     */
    public static function createNative(array $options = []): VCRHttpClient
    {
        return new VCRHttpClient(new NativeHttpClient($options));
    }

    /**
     * Create a VCR-compatible HttpClient using Symfony's factory.
     *
     * This wraps Symfony\Component\HttpClient\HttpClient::create() to return
     * a VCRHttpClient instead. Works with code transformation.
     *
     * Usage in tests:
     *   $client = \VCR\LibraryHooks\SymfonyHttpClientHook::create();
     *
     * @param array<string, mixed> $options HttpClient options
     */
    public static function create(array $options = []): VCRHttpClient
    {
        return new VCRHttpClient(\Symfony\Component\HttpClient\HttpClient::create($options));
    }
}
