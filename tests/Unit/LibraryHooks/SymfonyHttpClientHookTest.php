<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\LibraryHooks;

use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\SymfonyHttpClientCodeTransform;
use VCR\Configuration;
use VCR\LibraryHooks\SymfonyHttpClientHook;
use VCR\Response;
use VCR\Util\StreamProcessor;

final class SymfonyHttpClientHookTest extends TestCase
{
    protected Configuration $config;
    protected SymfonyHttpClientHook $hook;

    protected function setUp(): void
    {
        $this->config = new Configuration();
        $this->hook = new SymfonyHttpClientHook(
            new SymfonyHttpClientCodeTransform(),
            new StreamProcessor($this->config)
        );
    }

    protected function tearDown(): void
    {
        $this->hook->disable();
    }

    public function testShouldBeDisabledInitially(): void
    {
        $this->assertFalse($this->hook->isEnabled(), 'Initially the SymfonyHttpClientHook should be disabled.');
    }

    public function testShouldBeEnabledAfterEnabling(): void
    {
        $this->assertFalse($this->hook->isEnabled(), 'Initially the SymfonyHttpClientHook should be disabled.');

        $this->hook->enable($this->getTestCallback());
        $this->assertTrue($this->hook->isEnabled(), 'After enabling the SymfonyHttpClientHook should be enabled.');

        $this->hook->disable();
        $this->assertFalse($this->hook->isEnabled(), 'After disabling the SymfonyHttpClientHook should be disabled.');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testShouldNotThrowErrorWhenDisabledTwice(): void
    {
        $this->hook->disable();
        $this->hook->disable();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testShouldNotThrowErrorWhenEnabledTwice(): void
    {
        $this->hook->enable($this->getTestCallback());
        $this->hook->enable($this->getTestCallback());
        $this->hook->disable();
    }

    public function testGetRequestCallbackReturnsNull(): void
    {
        $this->assertNull(SymfonyHttpClientHook::getRequestCallback(), 'Request callback should be null when not enabled.');
    }

    public function testGetRequestCallbackReturnsCallbackWhenEnabled(): void
    {
        $callback = $this->getTestCallback();
        $this->hook->enable($callback);

        $this->assertSame($callback, SymfonyHttpClientHook::getRequestCallback(), 'Request callback should be set when enabled.');

        $this->hook->disable();
    }

    public function testCreateCurlReturnsVCRHttpClient(): void
    {
        $client = SymfonyHttpClientHook::createCurl();

        $this->assertInstanceOf(
            \VCR\VCRHttpClient::class,
            $client,
            'createCurl() should return a VCRHttpClient instance.'
        );
    }

    public function testCreateCurlWithOptions(): void
    {
        $client = SymfonyHttpClientHook::createCurl(['timeout' => 30]);

        $this->assertInstanceOf(
            \VCR\VCRHttpClient::class,
            $client,
            'createCurl() with options should return a VCRHttpClient instance.'
        );
    }

    public function testCreateNativeReturnsVCRHttpClient(): void
    {
        $client = SymfonyHttpClientHook::createNative();

        $this->assertInstanceOf(
            \VCR\VCRHttpClient::class,
            $client,
            'createNative() should return a VCRHttpClient instance.'
        );
    }

    public function testCreateNativeWithOptions(): void
    {
        $client = SymfonyHttpClientHook::createNative(['timeout' => 30]);

        $this->assertInstanceOf(
            \VCR\VCRHttpClient::class,
            $client,
            'createNative() with options should return a VCRHttpClient instance.'
        );
    }

    public function testDisableClearsRequestCallback(): void
    {
        $this->hook->enable($this->getTestCallback());
        $this->assertNotNull(SymfonyHttpClientHook::getRequestCallback(), 'Request callback should be set after enable.');

        $this->hook->disable();
        $this->assertNull(SymfonyHttpClientHook::getRequestCallback(), 'Request callback should be null after disable.');
    }

    public function testCreateCurlWrapsWithVCRHttpClient(): void
    {
        $client = SymfonyHttpClientHook::createCurl(['timeout' => 30]);

        // Verify it's a VCRHttpClient wrapping a CurlHttpClient
        $this->assertInstanceOf(\VCR\VCRHttpClient::class, $client);

        // Test it can make requests (passthrough when VCR not active)
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $wrappedClient = $property->getValue($client);

        $this->assertInstanceOf(\Symfony\Component\HttpClient\CurlHttpClient::class, $wrappedClient);
    }

    public function testCreateNativeWrapsWithVCRHttpClient(): void
    {
        $client = SymfonyHttpClientHook::createNative(['timeout' => 30]);

        // Verify it's a VCRHttpClient wrapping a NativeHttpClient
        $this->assertInstanceOf(\VCR\VCRHttpClient::class, $client);

        // Test it wraps the correct client type
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $wrappedClient = $property->getValue($client);

        $this->assertInstanceOf(\Symfony\Component\HttpClient\NativeHttpClient::class, $wrappedClient);
    }

    public function testEnableRegistersCodeTransformer(): void
    {
        $this->assertFalse($this->hook->isEnabled());

        $this->hook->enable($this->getTestCallback());

        $this->assertTrue($this->hook->isEnabled());

        // After enabling, code transformation should be active
        // This means new CurlHttpClient() calls will be transformed
        $this->assertNotNull(SymfonyHttpClientHook::getRequestCallback());
    }

    public function testMultipleClientsCanBeCreated(): void
    {
        $client1 = SymfonyHttpClientHook::createCurl(['timeout' => 10]);
        $client2 = SymfonyHttpClientHook::createCurl(['timeout' => 20]);
        $client3 = SymfonyHttpClientHook::createNative(['timeout' => 30]);

        $this->assertInstanceOf(\VCR\VCRHttpClient::class, $client1);
        $this->assertInstanceOf(\VCR\VCRHttpClient::class, $client2);
        $this->assertInstanceOf(\VCR\VCRHttpClient::class, $client3);

        $this->assertNotSame($client1, $client2);
        $this->assertNotSame($client2, $client3);
    }

    protected function getTestCallback(): \Closure
    {
        return \Closure::fromCallable(fn () => new Response('200', [], 'test response'));
    }
}
