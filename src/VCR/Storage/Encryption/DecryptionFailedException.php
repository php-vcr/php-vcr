<?php

declare(strict_types=1);

namespace VCR\Storage\Encryption;

class DecryptionFailedException extends \RuntimeException
{
    public static function forField(string $fieldPath): self
    {
        return new self(\sprintf(
            'Could not decrypt "%s". The key may be wrong, the cassette may have been altered, or the value may belong to a different field.',
            $fieldPath
        ));
    }

    public static function malformed(string $fieldPath): self
    {
        return new self(\sprintf('The encrypted value in "%s" is malformed.', $fieldPath));
    }
}
