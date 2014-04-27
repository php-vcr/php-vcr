<?php

namespace VCR;

use Assert\InvalidArgumentException;

class VCRException extends InvalidArgumentException
{
    const LIBRARY_HOOK_DISABLED = 500;

    public function __construct($message, $code, $propertyPath = null, $value = null, array $constraints = array())
    {
        parent::__construct($message, $code, $propertyPath, $value, $constraints);
    }
}
