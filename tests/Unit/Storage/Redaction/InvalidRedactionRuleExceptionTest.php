<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\InvalidRedactionRuleException;

final class InvalidRedactionRuleExceptionTest extends TestCase
{
    public function testEmptyPlaceholderExplainsWhyAnEmptyPlaceholderIsUseless(): void
    {
        $exception = InvalidRedactionRuleException::emptyPlaceholder();

        $this->assertStringContainsString('must not be empty', $exception->getMessage());
    }

    public function testUnsupportedSecretSourceNamesThePlaceholderAndTheGivenType(): void
    {
        $exception = InvalidRedactionRuleException::unsupportedSecretSource('{{API_KEY}}', 'int');

        $this->assertStringContainsString('{{API_KEY}}', $exception->getMessage());
        $this->assertStringContainsString('int', $exception->getMessage());
    }

    public function testWildcardHeaderWithSourceNamesTheWildcardAndTheWayOut(): void
    {
        $exception = InvalidRedactionRuleException::wildcardHeaderWithSource('*');

        $this->assertStringContainsString('"*"', $exception->getMessage());
        $this->assertStringContainsString('Name the header', $exception->getMessage());
    }

    public function testUnsupportedScopeNamesTheRejectedScopeAndTheAcceptedOnes(): void
    {
        $exception = InvalidRedactionRuleException::unsupportedScope('requset');

        $this->assertStringContainsString('requset', $exception->getMessage());
        $this->assertStringContainsString('request', $exception->getMessage());
        $this->assertStringContainsString('response', $exception->getMessage());
        $this->assertStringContainsString('both', $exception->getMessage());
    }
}
