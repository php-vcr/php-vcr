<?php

namespace VCR\CodeTransform;

use function in_array;
use function stream_get_filters;
use VCR\Util\Assertion;

/**
 * A stream wrapper filter to transform code.
 *
 * @package VCR\CodeTransform
 */
abstract class AbstractCodeTransform extends \php_user_filter
{
    const NAME = 'vcr_abstract_filter';

    /**
     * Attaches the current filter to a stream.
     */
    public function register(): void
    {
        if (!in_array(static::NAME, stream_get_filters(), true)) {
            $isRegistered = stream_filter_register(static::NAME, get_called_class());
            Assertion::true($isRegistered, sprintf('Failed registering stream filter "%s" on stream "%s"', get_called_class(), static::NAME));
        }
    }

    /**
     * Applies the current filter to a provided stream.
     *
     * @param resource $in
     * @param resource $out
     * @param int      $consumed
     * @param bool     $closing
     *
     * @return int PSFS_PASS_ON
     *
     * @link http://www.php.net/manual/en/php-user-filter.filter.php
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = $this->transformCode($bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    /**
     * Transcodes the provided data to whatever.
     *
     * @param string $code
     *
     * @return string
     */
    abstract protected function transformCode(string $code): string;
}
