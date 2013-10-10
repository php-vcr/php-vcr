<?php

namespace VCR\LibraryHooks;


abstract class AbstractFilter extends \PHP_User_Filter implements FilterInterface
{

    protected $isRegistered = false;

    /**
     * @return bool true on success or false on failure.
     */
    public function register()
    {
        if (!$this->isRegistered) {

            $this->isRegistered = stream_filter_register(static::NAME, __CLASS__);
        }

        return $this->isRegistered;
    }

    /**
     * @param resource $in
     * @param resource $out
     * @param int $consumed
     * @param bool $closing
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
