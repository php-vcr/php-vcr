<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

/**
 * The side(s) of a recorded HTTP interaction a redaction rule applies to.
 *
 * Kept as class constants rather than a native enum so the codebase stays compatible with the
 * PHP 8.0 floor this project supports. Because a plain string cannot enforce its own domain,
 * {@see self::assertValid()} is what keeps a typo from silently turning a rule into a no-op.
 */
final class Scope
{
    public const REQUEST = 'request';
    public const RESPONSE = 'response';
    public const BOTH = 'both';

    private function __construct()
    {
    }

    /**
     * Whether a rule declaring $scope acts on $side.
     *
     * @param string $scope the scope a rule declares, one of this class' three constants
     * @param string $side  the side being considered, `Scope::REQUEST` or `Scope::RESPONSE` — also
     *                      the name of the container a recording keeps that side under
     */
    public static function includes(string $scope, string $side): bool
    {
        return self::BOTH === $scope || $scope === $side;
    }

    /**
     * Rejects a scope string that is not one of this class' three constants.
     *
     * @phpstan-assert self::REQUEST|self::RESPONSE|self::BOTH $scope
     *
     * @throws InvalidRedactionRuleException if $scope is not a recognised scope
     */
    public static function assertValid(string $scope): void
    {
        if (!\in_array($scope, [self::REQUEST, self::RESPONSE, self::BOTH], true)) {
            throw InvalidRedactionRuleException::unsupportedScope($scope);
        }
    }
}
