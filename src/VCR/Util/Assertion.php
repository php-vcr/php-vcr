<?php

namespace VCR\Util;

use Assert\Assertion as BaseAssertion;
use VCR\VCRException;

class Assertion extends BaseAssertion
{
    protected static $exceptionClass = 'VCR\VCRException';

    const INVALID_CALLABLE = 910;

    /**
     * Assert that the value is callable.
     *
     * @param  mixed  $value Variable to check for a callable.
     * @param  string $message Exception message to show if value is not a callable.
     * @param  null   $propertyPath
     * @throws \VCR\VCRException If specified value is not a callable.
     *
     * @return bool
     */
    public static function isCallable($value, $message = null, string $propertyPath = null): bool
    {
        if (! is_callable($value)) {
            throw new VCRException($message, self::INVALID_CALLABLE, $propertyPath);
        }

        return true;
    }
}
