<?php

namespace VCR\CodeTransform;

use lapistano\ProxyObject\ProxyBuilder;
use PHPUnit\Framework\TestCase;

class SoapCodeTransformTest extends TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $proxy = new ProxyBuilder('\VCR\CodeTransform\SoapCodeTransform');
        $filter = $proxy
            ->setMethods(array('transformCode'))
            ->getProxy();

        $this->assertEquals($expected, $filter->transformCode($code));
    }

    public function codeSnippetProvider()
    {
        return array(
          array('new \VCR\Util\SoapClient(', 'new \SoapClient('),
          array('new \VCR\Util\SoapClient(', 'new SoapClient('),
          array('extends \VCR\Util\SoapClient', 'extends \SoapClient'),
          array("extends \\VCR\\Util\\SoapClient\n", "extends \\SoapClient\n"),
          array('new SoapClientExtended(', 'new SoapClientExtended('),
          array('new \SoapClientExtended(', 'new \SoapClientExtended('),
        );
    }
}
