<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\InvalidRedactionRuleException;
use VCR\Storage\Redaction\Scope;

final class ScopeTest extends TestCase
{
    /**
     * @dataProvider scopeProvider
     */
    public function testScopeHasTheExpectedValue(string $expectedValue, string $scope): void
    {
        $this->assertSame($expectedValue, $scope);
    }

    /** @return array<string, string[]> */
    public static function scopeProvider(): array
    {
        return [
            'request' => ['request', Scope::REQUEST],
            'response' => ['response', Scope::RESPONSE],
            'both' => ['both', Scope::BOTH],
        ];
    }

    public function testAllScopesAreDistinctFromEachOther(): void
    {
        $this->assertSame(
            [Scope::REQUEST, Scope::RESPONSE, Scope::BOTH],
            array_unique([Scope::REQUEST, Scope::RESPONSE, Scope::BOTH])
        );
    }

    /**
     * @dataProvider inclusionProvider
     */
    public function testIncludesReportsWhetherAScopeCoversASide(bool $expected, string $scope, string $side): void
    {
        $this->assertSame($expected, Scope::includes($scope, $side));
    }

    /**
     * @return array<string,array{0:bool,1:string,2:string}>
     */
    public static function inclusionProvider(): array
    {
        return [
            'request covers request' => [true, Scope::REQUEST, Scope::REQUEST],
            'request does not cover response' => [false, Scope::REQUEST, Scope::RESPONSE],
            'response covers response' => [true, Scope::RESPONSE, Scope::RESPONSE],
            'response does not cover request' => [false, Scope::RESPONSE, Scope::REQUEST],
            'both covers request' => [true, Scope::BOTH, Scope::REQUEST],
            'both covers response' => [true, Scope::BOTH, Scope::RESPONSE],
        ];
    }

    /**
     * @dataProvider validScopeProvider
     *
     * @doesNotPerformAssertions
     */
    public function testAssertValidAcceptsEveryKnownScope(string $scope): void
    {
        Scope::assertValid($scope);
    }

    /**
     * @return array<string,string[]>
     */
    public static function validScopeProvider(): array
    {
        return [
            'request' => [Scope::REQUEST],
            'response' => [Scope::RESPONSE],
            'both' => [Scope::BOTH],
        ];
    }

    /**
     * @dataProvider invalidScopeProvider
     */
    public function testAssertValidRejectsAnUnknownScopeInsteadOfSilentlyMakingTheRuleANoOp(string $scope): void
    {
        $this->expectException(InvalidRedactionRuleException::class);

        Scope::assertValid($scope);
    }

    /**
     * @return array<string,string[]>
     */
    public static function invalidScopeProvider(): array
    {
        return [
            'typo' => ['requset'],
            'empty' => [''],
            'wrong case' => ['Request'],
            'plural' => ['requests'],
        ];
    }
}
