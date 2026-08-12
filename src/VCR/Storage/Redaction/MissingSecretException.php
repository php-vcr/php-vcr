<?php

declare(strict_types=1);

namespace VCR\Storage\Redaction;

class MissingSecretException extends \RuntimeException
{
    public static function forPlaceholder(string $placeholder): self
    {
        return new self(\sprintf(
            'The replacement source for placeholder "%s" resolved to an empty value.',
            $placeholder
        ));
    }
}
