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
     * A bucket object returned by stream_bucket_make_writeable.
     *
     * @link http://php.net/manual/en/function.stream-bucket-make-writeable.php
     * @var object
     */
    protected $bucket;

    /**
     * Buffer which stores the content of a file for transforming.
     *
     * @var string
     */
    protected $buffer;

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
     * @return int PSFS_PASS_ON | PSFS_FEED_ME
     *
     * @link Implementation adapted from http://www.codediesel.com/php/creating-custom-stream-filters/
     * @link http://www.php.net/manual/en/php-user-filter.filter.php
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        // Read all the stream data and store it.
        while($bucket = stream_bucket_make_writeable($in)) {
            $this->buffer .= $bucket->data;
            $this->bucket = $bucket;
            $consumed = 0;
        }

        // After reading all data, run the transformation.
        if ($closing) {
            $consumed += strlen($this->buffer);

            $this->bucket->data = $this->transformCode($this->buffer);
            $this->bucket->datalen = strlen($this->bucket->data);

            if(!empty($this->bucket->data)) {
                stream_bucket_append($out, $this->bucket);
            }

            return PSFS_PASS_ON;
        }

        return PSFS_FEED_ME;
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
