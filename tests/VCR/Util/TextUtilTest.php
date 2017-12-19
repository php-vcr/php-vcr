<?php

namespace VCR\Util;

use PHPUnit\Framework\TestCase;

/**
 * Tests TextUtil methods.
 */
class TextUtilTest extends TestCase
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
