<?php

declare(strict_types=1);

namespace VCR\CodeTransform;

use VCR\Util\Assertion;

class SoapCodeTransform extends AbstractCodeTransform
{
    public const NAME = 'vcr_soap';

    /**
     * @var string[]
     */
    private static $replacements = [
        'new \VCR\Util\SoapClient(',
        'extends \VCR\Util\SoapClient',
    ];

    /**
     * @var string[]
     */
    private static $patterns = [
        '@new\s+\\\?SoapClient\W*\(@i',
        '@extends\s+\\\?SoapClient\b@i',
    ];

    protected function transformCode(string $code): string
    {
        $transformedCode = preg_replace(self::$patterns, self::$replacements, $code);
        Assertion::string($transformedCode);

        return $transformedCode;
    }
}
