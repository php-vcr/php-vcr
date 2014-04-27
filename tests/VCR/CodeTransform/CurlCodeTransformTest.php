<?php

namespace VCR\CodeTransform;

use lapistano\ProxyObject\ProxyBuilder;

class CurlCodeTransformTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $proxy = new ProxyBuilder('\VCR\CodeTransform\CurlCodeTransform');
        $filter = $proxy
            ->setMethods(array('transformCode'))
            ->getProxy();

        $this->assertEquals($expected, $filter->transformCode($code));
    }

    public function codeSnippetProvider()
    {
        return array(
            array('\VCR\LibraryHooks\CurlHook::curl_init(', 'CURL_INIT ('),
            array('\VCR\LibraryHooks\CurlHook::curl_exec(', 'curl_exec('),
            array('\VCR\LibraryHooks\CurlHook::curl_getinfo(', 'curl_getinfo('),
            array('\VCR\LibraryHooks\CurlHook::curl_setopt(', 'curl_setopt('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_add_handle(', 'curl_multi_add_handle('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_remove_handle(', 'curl_multi_remove_handle('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_exec(', 'curl_multi_exec('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_info_read(', 'curl_multi_info_read('),

            array('\VCR\LibraryHooks\CurlHook::curl_init(', '\\CURL_INIT ('),
            array('\VCR\LibraryHooks\CurlHook::curl_exec(', '\\curl_exec('),
            array('\VCR\LibraryHooks\CurlHook::curl_getinfo(', '\\curl_getinfo('),
            array('\VCR\LibraryHooks\CurlHook::curl_setopt(', '\\curl_setopt('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_add_handle(', '\\curl_multi_add_handle('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_remove_handle(', '\\curl_multi_remove_handle('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_exec(', '\\curl_multi_exec('),
            array('\VCR\LibraryHooks\CurlHook::curl_multi_info_read(', '\\curl_multi_info_read('),

            array('SomeClass::CURL_INIT (', 'SomeClass::CURL_INIT ('),
            array('SomeClass::curl_exec(', 'SomeClass::curl_exec('),
            array('SomeClass::curl_getinfo(', 'SomeClass::curl_getinfo('),
            array('SomeClass::curl_setopt(', 'SomeClass::curl_setopt('),
            array('SomeClass::curl_multi_add_handle(', 'SomeClass::curl_multi_add_handle('),
            array('SomeClass::curl_multi_remove_handle(', 'SomeClass::curl_multi_remove_handle('),
            array('SomeClass::curl_multi_exec(', 'SomeClass::curl_multi_exec('),
            array('SomeClass::curl_multi_info_read(', 'SomeClass::curl_multi_info_read('),

            array('$object->CURL_INIT (', '$object->CURL_INIT ('),
            array('$object->curl_exec(', '$object->curl_exec('),
            array('$object->curl_getinfo(', '$object->curl_getinfo('),
            array('$object->curl_setopt(', '$object->curl_setopt('),
            array('$object->curl_multi_add_handle(', '$object->curl_multi_add_handle('),
            array('$object->curl_multi_remove_handle(', '$object->curl_multi_remove_handle('),
            array('$object->curl_multi_exec(', '$object->curl_multi_exec('),
            array('$object->curl_multi_info_read(', '$object->curl_multi_info_read('),

        );
    }
}
