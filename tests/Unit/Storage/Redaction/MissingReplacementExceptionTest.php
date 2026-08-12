<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\MissingReplacementException;

final class MissingReplacementExceptionTest extends TestCase
{
    public function testForRuleNamesTheOffendingRule(): void
    {
        $exception = MissingReplacementException::forRule('redact password field');

        $this->assertStringContainsString('redact password field', $exception->getMessage());
    }

    public function testForRuleStatesTheTwoWaysOut(): void
    {
        $message = MissingReplacementException::forRule('redact password field')->getMessage();

        $this->assertStringContainsString('replacement', $message);
        $this->assertStringContainsString('allowIrreversibleRequestRedaction()', $message);
    }
}
