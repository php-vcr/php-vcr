<?php

namespace VCR\CodeTransform;

use lapistano\ProxyObject\ProxyBuilder;

class StreamProcessorCodeTransformTest extends \PHPUnit_Framework_TestCase
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
            array('\VCR\Util\StreamProcessor::is_writable(', 'is_writable ('),
            array('\VCR\Util\StreamProcessor::is_writable(', '\\is_writable ('),
            array('SomeClass::is_writable (', 'SomeClass::is_writable ('),
            array('$object->is_writable (', '$object->is_writable ('),
            array('function something_is_writable(', 'function something_is_writable('),

            array('\VCR\Util\StreamProcessor::is_writable(', 'is_writeable ('),
            array('SomeClass::is_writeable (', 'SomeClass::is_writeable ('),
            array('$object->is_writeable (', '$object->is_writeable ('),
            array('function something_is_writeable(', 'function something_is_writeable(')
        );
    }
}
