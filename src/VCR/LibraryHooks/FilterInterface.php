<?php

namespace VCR\LibraryHooks;


interface FilterInterface
{
    /**
     *
     * @return bool true on success or false on failure.
     */
    public function register();

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
    public function filter($in, $out, &$consumed, $closing);
}
