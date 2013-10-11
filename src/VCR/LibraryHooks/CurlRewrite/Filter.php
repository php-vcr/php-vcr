<?php

namespace VCR\LibraryHooks\CurlRewrite;

use VCR\LibraryHooks\AbstractFilter;

class Filter extends AbstractFilter
{
    const NAME = 'vcr_curl_rewrite';

    private static $replacements = array(
        'curl_init('    => '\VCR\LibraryHooks\CurlRewrite::curl_init(',
        'curl_exec('    => '\VCR\LibraryHooks\CurlRewrite::curl_exec(',
        'curl_getinfo(' => '\VCR\LibraryHooks\CurlRewrite::curl_getinfo(',
        'curl_setopt('  => '\VCR\LibraryHooks\CurlRewrite::curl_setopt(',
        'curl_multi_add_handle('    => '\VCR\LibraryHooks\CurlRewrite::multiAddHandle(',
        'curl_multi_remove_handle(' => '\VCR\LibraryHooks\CurlRewrite::multiRemoveHandle(',
        'curl_multi_exec('          => '\VCR\LibraryHooks\CurlRewrite::multiExec(',
        'curl_multi_info_read('     => '\VCR\LibraryHooks\CurlRewrite::multiInfoRead('
    );

    /**
     * @param string $code
     *
     * @return mixed
     */
    protected function transformCode($code)
    {
        return str_replace(
            array_keys(self::$replacements),
            array_values(self::$replacements),
            $code
        );
    }
}
