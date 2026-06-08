<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Native;

use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use VCR\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Connection-failure behaviour for Symfony NativeHttpClient.
 * The "with VCR" test is skipped — same headers-as-array limitation as #329.
 */
final class ErrorTest extends AbstractIntegrationTestCase
{
    private const UNBOUND_URL = 'http://localhost:9959/get';

    public function testConnectExceptionWithoutVcr(): void
    {
        $caught = false;
        try {
            $response = (new NativeHttpClient())->request('GET', self::UNBOUND_URL);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'NativeHttpClient must throw TransportException on connection failure');
    }

    public function testConnectExceptionPassesThroughWithVcr(): void
    {
        $this->markTestSkipped('NativeHttpClient: headers as array in stream context. See #329.');

        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-native-error.yml');

        $caught = false;
        try {
            $response = (new NativeHttpClient())->request('GET', self::UNBOUND_URL);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $caught = true;
        } finally {
            \VCR\VCR::turnOff();
        }

        $this->assertTrue($caught, 'TransportException must propagate through VCR');
    }
}
