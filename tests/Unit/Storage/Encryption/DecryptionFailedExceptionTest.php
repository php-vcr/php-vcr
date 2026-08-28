<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Encryption;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Encryption\DecryptionFailedException;

final class DecryptionFailedExceptionTest extends TestCase
{
    public function testIsARuntimeException(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, DecryptionFailedException::forField('request.body'));
    }

    public function testForFieldNamesTheAffectedField(): void
    {
        $exception = DecryptionFailedException::forField('request.headers.Authorization');

        $this->assertStringContainsString('request.headers.Authorization', $exception->getMessage());
    }

    public function testForFieldListsThePlausibleCauses(): void
    {
        $message = DecryptionFailedException::forField('request.body')->getMessage();

        $this->assertStringContainsString('key', $message);
        $this->assertStringContainsString('altered', $message);
        $this->assertStringContainsString('different field', $message);
    }

    public function testMalformedNamesTheAffectedField(): void
    {
        $exception = DecryptionFailedException::malformed('response.body');

        $this->assertStringContainsString('response.body', $exception->getMessage());
        $this->assertStringContainsString('malformed', $exception->getMessage());
    }
}
