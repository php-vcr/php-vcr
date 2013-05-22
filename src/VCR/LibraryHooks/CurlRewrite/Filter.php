<?php

namespace VCR\LibraryHooks\CurlRewrite;

class Filter extends \PHP_User_Filter
{
    const NAME = 'vcr_curl_rewrite';
    const REWRITE_CLASS = '\VCR\LibraryHooks\CurlRewrite';

    private static $replacements = array(
        'curl_init'    => '\VCR\LibraryHooks\CurlRewrite::curl_init',
        'curl_exec'    => '\VCR\LibraryHooks\CurlRewrite::curl_exec',
        'curl_getinfo' => '\VCR\LibraryHooks\CurlRewrite::curl_getinfo',
        'curl_setopt'  => '\VCR\LibraryHooks\CurlRewrite::curl_setopt'
    );

    public static function register()
    {
        stream_filter_register(self::NAME, __CLASS__);
    }

    function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = $this->transformCode($bucket->data);
            // var_dump($bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }

    public function transformCode($code)
    {
        return str_replace(
            array_keys(self::$replacements),
            array_values(self::$replacements),
            $code
        );
    }


}
Filter::register();