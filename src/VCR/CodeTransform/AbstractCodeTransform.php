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

    /** @var string The code to transform */
    private  $data = "";

    /** @var stdClass The clast bucket we operated on */
    private  $lastBucket = "";
    
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
            $this->data = $this->data.$bucket->data;
            if ($bucket !== null) {
                $this->lastBucket = $bucket;
            }
        }

        if ($closing) {
            $this->lastBucket->data = $this->transformCode($this->data);
            $consumed += $this->lastBucket->datalen;
            stream_bucket_append($out, $this->lastBucket);
            $this->data = null;
            $this->lastBucket = null;
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
