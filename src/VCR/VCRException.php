<?php

declare(strict_types=1);

namespace VCR;

use Assert\InvalidArgumentException;

class VCRException extends InvalidArgumentException
{
    public const LIBRARY_HOOK_DISABLED = 500;
    public const REQUEST_ERROR = 600;
}
