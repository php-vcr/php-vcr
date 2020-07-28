<?php

namespace VCR;

use Assert\InvalidArgumentException;

class VCRException extends InvalidArgumentException
{
    const LIBRARY_HOOK_DISABLED = 500;
    const REQUEST_ERROR = 600;
}
