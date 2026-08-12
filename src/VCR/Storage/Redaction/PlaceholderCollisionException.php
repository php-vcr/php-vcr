<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

class PlaceholderCollisionException extends \RuntimeException
{
    public static function forPlaceholder(string $placeholder, string $path): self
    {
        return new self(\sprintf(
            'The value at "%s" already contains the placeholder "%s" before redaction.',
            $path,
            $placeholder
        ));
    }
}
