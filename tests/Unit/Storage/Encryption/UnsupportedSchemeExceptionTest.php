<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Encryption;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Encryption\UnsupportedSchemeException;

final class UnsupportedSchemeExceptionTest extends TestCase
{
    public function testIsARuntimeException(): void
    {
        $this->assertInstanceOf(
            \RuntimeException::class,
            UnsupportedSchemeException::forScheme('v99', 'request.body')
        );
    }

    public function testNamesBothTheSchemeAndTheField(): void
    {
        $exception = UnsupportedSchemeException::forScheme('v99', 'request.body');

        $this->assertStringContainsString('v99', $exception->getMessage());
        $this->assertStringContainsString('request.body', $exception->getMessage());
    }

    public function testIsNotADecryptionFailure(): void
    {
        $this->assertNotInstanceOf(
            \VCR\Storage\Encryption\DecryptionFailedException::class,
            UnsupportedSchemeException::forScheme('v99', 'request.body'),
            'A cassette written by a newer php-vcr is a different problem than a wrong key.'
        );
    }
}
