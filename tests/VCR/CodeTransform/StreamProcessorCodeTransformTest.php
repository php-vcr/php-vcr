<?php

namespace VCR\CodeTransform;

use lapistano\ProxyObject\ProxyBuilder;

class StreamProcessorTransformTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $proxy = new ProxyBuilder('\VCR\CodeTransform\StreamProcessorCodeTransform');
        $filter = $proxy
            ->setMethods(array('transformCode'))
            ->getProxy();

        $this->assertEquals($expected, $filter->transformCode($code));
    }

    public function codeSnippetProvider()
    {
        return array(
          array('\VCR\LibraryHooks\StreamWrapperHook::getLastResponseHeaders()', '$http_response_header'),
          array('$http_response_header =', '$http_response_header ='),
          array('\VCR\LibraryHooks\StreamWrapperHook::getLastResponseHeaders()[0]', '$http_response_header[0]'),
          array('count(\VCR\LibraryHooks\StreamWrapperHook::getLastResponseHeaders())', 'count($http_response_header)'),
        );
    }
}
