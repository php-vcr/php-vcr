<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\CodeTransform;

use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\SoapCodeTransform;

final class SoapCodeTransformTest extends TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode(string $expected, string $code): void
    {
        $codeTransform = new class extends SoapCodeTransform {
            // A proxy to access the protected transformCode method.
            public function publicTransformCode(string $code): string
            {
                return $this->transformCode($code);
            }
        };

        $this->assertEquals($expected, $codeTransform->publicTransformCode($code));
    }

    /** @return array<string[]> */
    public function codeSnippetProvider(): array
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
