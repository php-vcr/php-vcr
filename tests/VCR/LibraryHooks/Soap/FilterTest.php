<?php

namespace VCR\LibraryHooks\Soap;

use lapistano\ProxyObject\ProxyBuilder;

class FilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $proxy = new ProxyBuilder('\VCR\LibraryHooks\Soap\Filter');
        $filter = $proxy
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
            'extends \SoapClient' => array(
                'extends \VCR\Util\Soap\SoapClient',
                'extends \SoapClient'
            ),
            'extends \SoapClient with linebreak' => array(
                "extends \VCR\Util\Soap\SoapClient\n",
                "extends \SoapClient\n"
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
