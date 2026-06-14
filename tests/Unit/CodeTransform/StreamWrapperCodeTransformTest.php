<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\CodeTransform;

use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\StreamWrapperCodeTransform;

final class StreamWrapperCodeTransformTest extends TestCase
{
    /**
     * @dataProvider codeSnippetProvider
     */
    public function testTransformCode(string $expected, string $code): void
    {
        $codeTransform = new class extends StreamWrapperCodeTransform {
            // A proxy to access the protected transformCode method.
            public function publicTransformCode(string $code): string
            {
                return $this->transformCode($code);
            }
        };

        $this->assertEquals($expected, $codeTransform->publicTransformCode($code));
    }

    /** @return array<string[]> */
    public static function codeSnippetProvider(): array
    {
        return [
            // Positive: bare function call (case-insensitive)
            ['\VCR\LibraryHooks\StreamWrapperHook::streamGetMetaData(', 'stream_get_meta_data('],
            ['\VCR\LibraryHooks\StreamWrapperHook::streamGetMetaData(', 'STREAM_GET_META_DATA('],
            ['\VCR\LibraryHooks\StreamWrapperHook::streamGetMetaData(', 'Stream_Get_Meta_Data('],

            // Positive: with leading backslash
            ['\VCR\LibraryHooks\StreamWrapperHook::streamGetMetaData(', '\\stream_get_meta_data('],
            ['\VCR\LibraryHooks\StreamWrapperHook::streamGetMetaData(', '\\STREAM_GET_META_DATA('],

            // Guard: static call — must NOT be rewritten
            ['SomeClass::stream_get_meta_data(', 'SomeClass::stream_get_meta_data('],

            // Guard: method call — must NOT be rewritten
            ['$object->stream_get_meta_data(', '$object->stream_get_meta_data('],

            // Guard: identifier with underscore suffix — must NOT be rewritten
            ['function some_stream_get_meta_data(', 'function some_stream_get_meta_data('],
        ];
    }
}
