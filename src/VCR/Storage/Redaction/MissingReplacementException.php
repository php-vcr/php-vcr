<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

class MissingReplacementException extends \RuntimeException
{
    public static function forRule(string $description): self
    {
        return new self(\sprintf(
            'The redaction rule "%s" redacts request data but has no replacement source, which would break replay. Either supply a replacement source or call allowIrreversibleRequestRedaction().',
            $description
        ));
    }
}
