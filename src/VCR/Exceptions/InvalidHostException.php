<?php

declare(strict_types=1);

namespace VCR\Exceptions;

class InvalidHostException extends \InvalidArgumentException
{
    public static function create(?string $url): self
    {
        return new self(\sprintf('Could not read host from URL "%s". Please check the URL syntax.', $url));
    }
}
