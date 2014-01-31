<?php

namespace VCR\Filter;

use lapistano\ProxyObject\ProxyBuilder;

class CurlFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $proxy = new ProxyBuilder('\VCR\Filter\CurlFilter');
        $filter = $proxy
            ->setMethods(array('transformCode'))
            ->getProxy();

        $this->assertEquals($expected, $filter->transformCode($code));
    }

    public function codeSnippetProvider()
    {
        return array(
            'curl_init('                => array('\VCR\LibraryHooks\Curl::curl_init(', 'CURL_INIT ('),
            'curl_exec('                => array('\VCR\LibraryHooks\Curl::curl_exec(', 'curl_exec('),
            'curl_getinfo('             => array('\VCR\LibraryHooks\Curl::curl_getinfo(', 'curl_getinfo('),
            'curl_setopt('              => array('\VCR\LibraryHooks\Curl::curl_setopt(', 'curl_setopt('),
            'curl_multi_add_handle('    => array('\VCR\LibraryHooks\Curl::curl_multi_add_handle(', 'curl_multi_add_handle('),
            'curl_multi_remove_handle(' => array('\VCR\LibraryHooks\Curl::curl_multi_remove_handle(', 'curl_multi_remove_handle('),
            'curl_multi_exec('          => array('\VCR\LibraryHooks\Curl::curl_multi_exec(', 'curl_multi_exec('),
            'curl_multi_info_read('     => array('\VCR\LibraryHooks\Curl::curl_multi_info_read(', 'curl_multi_info_read('),
        );
    }
}
