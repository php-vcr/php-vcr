<?php

declare(strict_types=1);

namespace VCR\CodeTransform;

use VCR\Util\Assertion;

class CurlCodeTransform extends AbstractCodeTransform
{
    public const NAME = 'vcr_curl';

    /**
     * @var array<string, string>
     */
    private static $patterns = [
        '/(?<!::|->|\w_)\\\?curl_init\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_init(',
        '/(?<!::|->|\w_)\\\?curl_exec\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_exec(',
        '/(?<!::|->|\w_)\\\?curl_getinfo\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_getinfo(',
        '/(?<!::|->|\w_)\\\?curl_setopt\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_setopt(',
        '/(?<!::|->|\w_)\\\?curl_setopt_array\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_setopt_array(',
        '/(?<!::|->|\w_)\\\?curl_multi_add_handle\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_multi_add_handle(',
        '/(?<!::|->|\w_)\\\?curl_multi_remove_handle\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_multi_remove_handle(',
        '/(?<!::|->|\w_)\\\?curl_multi_exec\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_multi_exec(',
        '/(?<!::|->|\w_)\\\?curl_multi_info_read\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_multi_info_read(',
        '/(?<!::|->|\w_)\\\?curl_multi_getcontent\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_multi_getcontent(',
        '/(?<!::|->|\w_)\\\?curl_reset\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_reset(',
        '/(?<!::|->|\w_)\\\?curl_error\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_error(',
        '/(?<!::|->|\w_)\\\?curl_errno\s*\(/i' => '\VCR\LibraryHooks\CurlHook::curl_errno(',
    ];

    protected function transformCode(string $code): string
    {
        $transformedCode = preg_replace(array_keys(self::$patterns), array_values(self::$patterns), $code);
        Assertion::string($transformedCode);

        return $transformedCode;
    }
}
