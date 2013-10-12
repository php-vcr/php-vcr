<?php

namespace VCR\LibraryHooks\Soap;


use VCR\VCR_TestCase;


class FilterTest extends VCR_TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $filter = $this->getProxyBuilder('\VCR\LibraryHooks\Soap\Filter')
            ->setMethods(array('transformCode'))
            ->getProxy();

        $this->assertEquals($expected, $filter->transformCode($code));
    }

    public function codeSnippetProvider()
    {
        return array(
            'new \SoapClient' => array(
                'new \VCR\Util\Soap\SoapClient(',
                'new \SoapClient('
            ),
            'new SoapClient' => array(
                'new \VCR\Util\Soap\SoapClient(',
                'new SoapClient('
            ),
            'new SoapClientExtended' => array(
                'new SoapClientExtended(',
                'new SoapClientExtended('
            ),
            'new \SoapClientExtended' => array(
                'new \SoapClientExtended(',
                'new \SoapClientExtended('
            ),
        );
    }
}
