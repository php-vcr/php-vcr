<?php

namespace VCR\Exceptions;

use InvalidArgumentException;

class InvalidHostException extends InvalidArgumentException
{
    public static function create(?string $url): self
    {
        return new self(sprintf('Could not read host from URL "%s". Please check the URL syntax.', $url));
    }
}
