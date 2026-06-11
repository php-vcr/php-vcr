<?php

declare(strict_types=1);

namespace VCR\CodeTransform;

use VCR\Util\Assertion;

class StreamWrapperCodeTransform extends AbstractCodeTransform
{
    public const NAME = 'vcr_stream_wrapper';

    /**
     * @var array<string, string>
     */
    private static $patterns = [
        '/(?<!::|->|\w_)\\\?stream_get_meta_data\s*\(/i' => '\VCR\LibraryHooks\StreamWrapperHook::streamGetMetaData(',
    ];

    protected function transformCode(string $code): string
    {
        $transformedCode = preg_replace(array_keys(self::$patterns), array_values(self::$patterns), $code);
        Assertion::string($transformedCode);

        return $transformedCode;
    }
}
