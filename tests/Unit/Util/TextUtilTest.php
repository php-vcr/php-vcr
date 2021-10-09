<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use VCR\Util\TextUtil;

final class TextUtilTest extends TestCase
{
    /**
     * @dataProvider curlMethodsProvider
     */
    public function testUnderscoreToLowerCamelcase(string $expected, string $method): void
    {
        $this->assertEquals($expected, TextUtil::underscoreToLowerCamelcase($method));
    }

    /** @return array<string, string[]> */
    public function curlMethodsProvider(): array
    {
        return [
            'curl_multi_add_handler' => ['curlMultiAddHandler', 'curl_multi_add_handler'],
            'curl_add_handler' => ['curlAddHandler', 'curl_add_handler'],
            'not a curl function' => ['curlExec', 'curl_exec'],
        ];
    }
}
