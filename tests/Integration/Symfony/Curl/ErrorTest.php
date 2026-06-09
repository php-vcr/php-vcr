<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Symfony\Curl;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use VCR\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Connection-failure behaviour for Symfony CurlHttpClient.
 */
final class ErrorTest extends AbstractIntegrationTestCase
{
    private const UNBOUND_URL = 'http://localhost:9959/get';

    public function testConnectExceptionWithoutVcr(): void
    {
        $caught = false;
        try {
            $response = (new CurlHttpClient())->request('GET', self::UNBOUND_URL);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'CurlHttpClient must throw TransportException on connection failure');
    }

    public function testConnectExceptionPassesThroughWithVcr(): void
    {
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('symfony-curl-error.yml');

        $caught = false;
        try {
            $response = (new CurlHttpClient())->request('GET', self::UNBOUND_URL);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $caught = true;
        } finally {
            \VCR\VCR::turnOff();
        }

        $this->assertTrue($caught, 'TransportException must propagate through VCR');
    }
}
