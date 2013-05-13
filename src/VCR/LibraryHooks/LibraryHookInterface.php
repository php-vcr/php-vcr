<?php

namespace VCR\LibraryHooks;

/**
 * Library hook interface.
 */
interface LibraryHookInterface
{
    const ENABLED = 'ENABLED';
    const DISABLED = 'DISABLED';

    public function __construct(\Closure $handleRequestCallback = null);

    public function enable();

    public function disable();

}