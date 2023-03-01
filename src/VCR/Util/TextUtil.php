<?php

declare(strict_types=1);

namespace VCR\Util;

class TextUtil
{
    /**
     * Returns lowercase camelcase from specified underscore text.
     *
     * Example: curl_multi_exec -> curlMultiExec
     */
    public static function underscoreToLowerCamelcase(string $underscore): string
    {
        return lcfirst(
            str_replace(
                ' ',
                '',
                ucwords(str_replace('_', ' ', $underscore))
            )
        );
    }
}
