<?php

namespace VCR\LibraryHooks;

/**
 * Library hook interface.
 */
interface LibraryHookInterface
{
    const ENABLED = 'ENABLED';
    const DISABLED = 'DISABLED';

    public function __construct(\Closure $handleRequestCallable = null);

    public function enable();

    public function disable();

}