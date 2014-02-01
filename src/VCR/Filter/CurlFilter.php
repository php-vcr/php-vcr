<?php

namespace VCR\Filter;

class CurlFilter extends AbstractFilter
{
    const NAME = 'vcr_curl';

    private static $replacements = array(
        '\VCR\LibraryHooks\CurlHook::curl_init(',
        '\VCR\LibraryHooks\CurlHook::curl_exec(',
        '\VCR\LibraryHooks\CurlHook::curl_getinfo(',
        '\VCR\LibraryHooks\CurlHook::curl_setopt(',
        '\VCR\LibraryHooks\CurlHook::curl_setopt_array(',
        '\VCR\LibraryHooks\CurlHook::curl_multi_add_handle(',
        '\VCR\LibraryHooks\CurlHook::curl_multi_remove_handle(',
        '\VCR\LibraryHooks\CurlHook::curl_multi_exec(',
        '\VCR\LibraryHooks\CurlHook::curl_multi_info_read('
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
     * @return string
     */
    protected function transformCode($code)
    {
        return preg_replace(self::$patterns, self::$replacements, $code);
    }
}
