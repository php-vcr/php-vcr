<?php

namespace VCR\CodeTransform;

/**
 * A stream wrapper filter to transform code.
 *
 * @package VCR\CodeTransform
 */
abstract class AbstractCodeTransform extends \PHP_User_Filter
{
    const NAME = 'vcr_abstract_filter';

    /**
     * Flag to signalize the current filter is registered.
     *
     * @var bool
     */
    protected $isRegistered = false;

    /**
     * Attaches the current filter to a stream.
     *
     * @return bool true on success or false on failure.
     */
    public function register()
    {
        if (!$this->isRegistered) {
            $this->isRegistered = stream_filter_register(static::NAME, get_called_class());
        }

        return $this->isRegistered;
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
    abstract protected function transformCode($code);
}
