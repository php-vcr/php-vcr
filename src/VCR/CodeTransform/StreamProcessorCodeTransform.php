<?php

namespace VCR\CodeTransform;

class StreamProcessorCodeTransform extends AbstractCodeTransform
{
    const NAME = 'vcr_streamprocessor';

    private static $patterns = array(
        // Substitute for the $http_response_header variable, unless it's used as an assignment.
        '/\$http_response_header\b(?!\s*=)/i' => '\VCR\LibraryHooks\StreamWrapperHook::getLastResponseHeaders()'
    );

    /**
     * @inheritdoc
     */
    protected function transformCode($code)
    {
        return preg_replace(array_keys(self::$patterns), array_values(self::$patterns), $code);
    }
}
