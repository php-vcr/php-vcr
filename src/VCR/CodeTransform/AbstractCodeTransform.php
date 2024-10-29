<?php

declare(strict_types=1);

namespace VCR\CodeTransform;

use VCR\Util\Assertion;

/**
 * A stream wrapper filter to transform code.
 */
abstract class AbstractCodeTransform extends \php_user_filter
{
    public const NAME = 'vcr_abstract_filter';

    private string $data = '';

    /**
     * Attaches the current filter to a stream.
     */
    public function register(): void
    {
        if (!\in_array(static::NAME, stream_get_filters(), true)) {
            $isRegistered = stream_filter_register(static::NAME, static::class);
            Assertion::true(
                $isRegistered,
                \sprintf('Failed registering stream filter "%s" on stream "%s"', static::class, static::NAME)
            );
        }
    }

    /**
     * Applies the current filter to a provided stream.
     *
     * @param resource $in
     * @param resource $out
     * @param int      $consumed
     *
     * @return int PSFS_PASS_ON
     *
     * @see http://www.php.net/manual/en/php-user-filter.filter.php
     */
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($buffer = stream_bucket_make_writeable($in)) {
            $this->data .= $buffer->data;
            $consumed += $buffer->datalen;
        }

        if (!$closing) {
            return \PSFS_FEED_ME;
        }

        $bucket = stream_bucket_new($this->stream, $this->transformCode($this->data));
        $this->data = '';
        stream_bucket_append($out, $bucket);

        return \PSFS_PASS_ON;
    }

    /**
     * Transcodes the provided data to whatever.
     */
    abstract protected function transformCode(string $code): string;
}
