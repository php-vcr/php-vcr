<?php

namespace VCR\LibraryHooks;

/**
 * Library hook interface.
 */
interface LibraryHookInterface
{
    const ENABLED = 'ENABLED';
    const DISABLED = 'DISABLED';

    public function enable(\Closure $handleRequestCallback);

    public function disable();

}