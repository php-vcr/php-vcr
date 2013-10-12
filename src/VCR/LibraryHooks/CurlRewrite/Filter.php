<?php

namespace VCR\LibraryHooks\CurlRewrite;

use VCR\LibraryHooks\AbstractFilter;

class Filter extends AbstractFilter
{
    const NAME = 'vcr_curl_rewrite';

    private static $replacements = array(
        '\VCR\LibraryHooks\CurlRewrite::curl_init(',
        '\VCR\LibraryHooks\CurlRewrite::curl_exec(',
        '\VCR\LibraryHooks\CurlRewrite::curl_getinfo(',
        '\VCR\LibraryHooks\CurlRewrite::curl_setopt(',
        '\VCR\LibraryHooks\CurlRewrite::curl_multi_add_handle(',
        '\VCR\LibraryHooks\CurlRewrite::curl_multi_remove_handle(',
        '\VCR\LibraryHooks\CurlRewrite::curl_multi_exec(',
        '\VCR\LibraryHooks\CurlRewrite::curl_multi_info_read('
    );

    private static $patterns = array(
        '@curl_init\s*\(@i',
        '@curl_exec\s*\(@i',
        '@curl_getinfo\s*\(@i',
        '@curl_setopt\s*\(@i',
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
