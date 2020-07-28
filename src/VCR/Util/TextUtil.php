<?php

namespace VCR\Util;

/**
 * TextUtil provides conversions between text based formats.
 */
class TextUtil
{
    /**
     * Returns lowercase camelcase from specified underscore text.
     *
     * Example: curl_multi_exec -> curlMultiExec
     *
     * @param string $underscore lowercased text
     *
     * @return string lowercase camelcased version of specified text
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
