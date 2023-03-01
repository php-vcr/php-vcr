<?php

declare(strict_types=1);

namespace VCR\LibraryHooks;

interface LibraryHook
{
    public const ENABLED = 'ENABLED';

    public const DISABLED = 'DISABLED';

    /**
     * Enables library hook which means that all of this library
     * http interactions are intercepted.
     */
    public function enable(\Closure $requestCallback): void;

    public function disable(): void;

    public function isEnabled(): bool;
}
