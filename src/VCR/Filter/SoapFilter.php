<?php

namespace VCR\Filter;

class SoapFilter extends AbstractFilter
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
     * @param string $code
     *
     * @return mixed
     */
    protected function transformCode($code)
    {
        return preg_replace(self::$patterns, self::$replacements, $code);
    }
}
