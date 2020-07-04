<?php

namespace VCR\CodeTransform;

use PHPUnit\Framework\TestCase;

class SoapCodeTransformTest extends TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode($expected, $code)
    {
        $codeTransform = new class() extends SoapCodeTransform {
            // A proxy to access the protected transformCode method.
            public function publicTransformCode(string $code): string
            {
                return $this->transformCode($code);
            }
        };

        $this->assertEquals($expected, $codeTransform->publicTransformCode($code));
    }

    public function codeSnippetProvider()
    {
        return [
          ['new \VCR\Util\SoapClient(', 'new \SoapClient('],
          ['new \VCR\Util\SoapClient(', 'new SoapClient('],
          ['extends \VCR\Util\SoapClient', 'extends \SoapClient'],
          ["extends \\VCR\\Util\\SoapClient\n", "extends \\SoapClient\n"],
          ["extends MySoapClientBuilder\n", "extends MySoapClientBuilder\n"],
          ["extends SoapClientFactory\n", "extends SoapClientFactory\n"],
          ['new SoapClientExtended(', 'new SoapClientExtended('],
          ['new \SoapClientExtended(', 'new \SoapClientExtended('],
        ];
    }
}
