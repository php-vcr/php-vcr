<?php

namespace VCR\LibraryHooks\Curl;

use VCR\LibraryHooks\AbstractFilter;

class Filter extends AbstractFilter
{
    const NAME = 'vcr_curl';

    private static $replacements = array(
        '\VCR\LibraryHooks\Curl::curl_init(',
        '\VCR\LibraryHooks\Curl::curl_exec(',
        '\VCR\LibraryHooks\Curl::curl_getinfo(',
        '\VCR\LibraryHooks\Curl::curl_setopt(',
        '\VCR\LibraryHooks\Curl::curl_setopt_array(',
        '\VCR\LibraryHooks\Curl::curl_multi_add_handle(',
        '\VCR\LibraryHooks\Curl::curl_multi_remove_handle(',
        '\VCR\LibraryHooks\Curl::curl_multi_exec(',
        '\VCR\LibraryHooks\Curl::curl_multi_info_read('
    );

    private static $patterns = array(
        '@curl_init\s*\(@i',
        '@curl_exec\s*\(@i',
        '@curl_getinfo\s*\(@i',
        '@curl_setopt\s*\(@i',
        '@curl_setopt_array\s*\(@i',
        '@curl_multi_add_handle\s*\(@i',
        '@curl_multi_remove_handle\s*\(@i',
        '@curl_multi_exec\s*\(@i',
        '@curl_multi_info_read\s*\(@i',
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
