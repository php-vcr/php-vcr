<?php

namespace VCR\Util;

use Assert\Assertion as BaseAssertion;
use VCR\VCRException;

class Assertion extends BaseAssertion
{
    protected static $exceptionClass = 'VCR\VCRException';

    public const INVALID_CALLABLE = 910;

    public static function isCurlResource($value, $message): bool
    {
        if(is_object($value) && get_class($value) == \CurlHandle::class) {
            return true;
        }

        return Assertion::isResource($value, $message);
    }

    /**
     * Assert that the value is callable.
     *
     * @param mixed  $value        variable to check for a callable
     * @param string $message      exception message to show if value is not a callable
     * @param null   $propertyPath
     *
     * @throws \VCR\VCRException if specified value is not a callable
     */
    public static function isCallable($value, $message = null, string $propertyPath = null): bool
    {
        if (!\is_callable($value)) {
            throw new VCRException($message, self::INVALID_CALLABLE, $propertyPath);
        }

        return true;
    }
}
