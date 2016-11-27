<?php

namespace VCR\CodeTransform;

class StreamProcessorCodeTransform extends AbstractCodeTransform
{
    const NAME = 'vcr_stream_processor';

    private static $patterns = array(
        '/(?<!::|->|\w_)\\\?is_writable\s*\(/i' => '\VCR\Util\StreamProcessor::is_writable(',
        '/(?<!::|->|\w_)\\\?is_writeable\s*\(/i' => '\VCR\Util\StreamProcessor::is_writable(',
    );

    /**
     * @inheritdoc
     */
    protected function transformCode($code)
    {
        return preg_replace(array_keys(self::$patterns), array_values(self::$patterns), $code);
    }
}
