<?php

declare(strict_types=1);

namespace VCR\Storage\Encryption;

class UnsupportedSchemeException extends \RuntimeException
{
    public static function forScheme(string $scheme, string $fieldPath): self
    {
        return new self(\sprintf(
            'The value in "%s" uses encryption scheme "%s", which this version of php-vcr cannot read.',
            $fieldPath,
            $scheme
        ));
    }
}
