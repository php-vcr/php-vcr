<?php

namespace VCR\LibraryHooks\Soap;

use VCR\LibraryHooks\AbstractFilter;

class Filter extends AbstractFilter
{
    const NAME = 'vcr_soap';

    private static $replacements = array(
        'new \VCR\Util\Soap\SoapClient(',
        'extends \VCR\Util\Soap\SoapClient',
    );

    private static $patterns = array(
        '@new\s+\\\?SoapClient\W*\(@i',
        '@extends\s+SoapClient\W*@i',
    );

    /**
     * @param string $code
     *
     * @return mixed
     */
    protected function transformCode($code)
    {
        return preg_replace(self::$patterns, self::$replacements, $code);
    }
}
