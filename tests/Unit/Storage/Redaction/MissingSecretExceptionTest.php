<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\MissingSecretException;

final class MissingSecretExceptionTest extends TestCase
{
    public function testForPlaceholderNamesThePlaceholder(): void
    {
        $exception = MissingSecretException::forPlaceholder('{{API_KEY}}');

        $this->assertStringContainsString('{{API_KEY}}', $exception->getMessage());
    }
}
