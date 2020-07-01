<?php

namespace VCR\LibraryHooks;

/**
 * Library hook interface.
 */
interface LibraryHook
{
    /**
     * @var string enabled status for a hook
     */
    const ENABLED = 'ENABLED';

    /**
     * @var string disabled status for a hook
     */
    const DISABLED = 'DISABLED';

    /**
     * Enables library hook which means that all of this library
     * http interactions are intercepted.
     *
     * @param \Closure $requestCallback callback which will be called when a request is intercepted
     *
     * @throws \VCR\VCRException when specified callback is not callable
     */
    public function enable(\Closure $requestCallback): void;

    /**
     * Disables library hook, so no http interactions of
     * this library are intercepted anymore.
     */
    public function disable(): void;

    /**
     * Returns true if library hook is enabled, false otherwise.
     *
     * @return bool true if library hook is enabled, false otherwise
     */
    public function isEnabled(): bool;
}
