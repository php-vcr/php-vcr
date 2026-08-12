<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\PlaceholderCollisionException;

final class PlaceholderCollisionExceptionTest extends TestCase
{
    public function testForPlaceholderNamesBothThePlaceholderAndThePath(): void
    {
        $exception = PlaceholderCollisionException::forPlaceholder('{{API_KEY}}', 'request.body');

        $this->assertStringContainsString('{{API_KEY}}', $exception->getMessage());
        $this->assertStringContainsString('request.body', $exception->getMessage());
    }
}
