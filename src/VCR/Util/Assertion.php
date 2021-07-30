<?php

namespace VCR\Util;

use Assert\Assertion as BaseAssertion;
use VCR\VCRException;

class Assertion extends BaseAssertion
{
    protected static $exceptionClass = 'VCR\VCRException';

    public const INVALID_CALLABLE = 910;

    public static function isCurlResource($value): bool
    {
        if(get_class($value) == CurlHandle::class) {
            return true;
        }

        $errorMessage = "Could not init curl with URL '{$request->getUrl()}'";

        if($value === false) {
            throw new \Exception($errorMessage);
        }

        return Assertion::isResource($value, $errorMessage);
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
