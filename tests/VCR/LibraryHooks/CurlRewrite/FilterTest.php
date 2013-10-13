<?php

namespace VCR\LibraryHooks\CurlRewrite;


use VCR\VCR_TestCase;


class FilterTest extends VCR_TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $filter = $this->getProxyBuilder('\VCR\LibraryHooks\CurlRewrite\Filter')
            ->setMethods(array('transformCode'))
            ->getProxy();

        $this->assertEquals($expected, $filter->transformCode($code));
    }

    public function codeSnippetProvider()
    {
        return array(
            'curl_init('                => array('\VCR\LibraryHooks\CurlRewrite::curl_init(', 'CURL_INIT ('),
            'curl_exec('                => array('\VCR\LibraryHooks\CurlRewrite::curl_exec(', 'curl_exec('),
            'curl_getinfo('             => array('\VCR\LibraryHooks\CurlRewrite::curl_getinfo(', 'curl_getinfo('),
            'curl_setopt('              => array('\VCR\LibraryHooks\CurlRewrite::curl_setopt(', 'curl_setopt('),
            'curl_multi_add_handle('    => array('\VCR\LibraryHooks\CurlRewrite::curl_multi_add_handle(', 'curl_multi_add_handle('),
            'curl_multi_remove_handle(' => array('\VCR\LibraryHooks\CurlRewrite::curl_multi_remove_handle(', 'curl_multi_remove_handle('),
            'curl_multi_exec('          => array('\VCR\LibraryHooks\CurlRewrite::curl_multi_exec(', 'curl_multi_exec('),
            'curl_multi_info_read('     => array('\VCR\LibraryHooks\CurlRewrite::curl_multi_info_read(', 'curl_multi_info_read('),
        );
    }
}
