<?php

namespace VCR\CodeTransform;

use VCR\Util\Assertion;

class SoapCodeTransform extends AbstractCodeTransform
{
    const NAME = 'vcr_soap';

    private static $replacements = array(
        'new \VCR\Util\SoapClient(',
        'extends \VCR\Util\SoapClient',
    );

    private static $patterns = array(
        '@new\s+\\\?SoapClient\W*\(@i',
        '@extends\s+\\\?SoapClient@i',
    );

    /**
     * @inheritdoc
     */
    protected function transformCode(string $code): string
    {
        $transformedCode = preg_replace(self::$patterns, self::$replacements, $code);
        Assertion::string($transformedCode);
        return $transformedCode;
    }
}
