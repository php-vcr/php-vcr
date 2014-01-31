<?php

namespace VCR\Util;

/**
 * Tests TextUtil methods.
 */
class TextUtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider curlMethodsProvider
     */
    public function testUnderscoreToLowerCamelcase($expected, $method)
    {
        $this->assertEquals($expected, TextUtil::underscoreToLowerCamelcase($method));
    }

    public function curlMethodsProvider()
    {
        return array(
            'curl_multi_add_handler' => array('curlMultiAddHandler', 'curl_multi_add_handler'),
            'curl_add_handler' => array('curlAddHandler', 'curl_add_handler'),
            'not a curl function' => array('curlExec', 'curl_exec'),
        );
    }
}
