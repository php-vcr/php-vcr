<?php
namespace VCR;

use Assert\Assertion as BaseAssertion;

class Assertion extends BaseAssertion
{
    protected static $exceptionClass = '\VCR\VCRException';

    const INVALID_CALLABLE = 910;

    /**
     * Assert that the value is callable.
     *
     * @param mixed $value
     * @param string $message
     * @return void
     * @throws Assert\AssertionFailedException
     */
    static public function isCallable($value, $message = null, $propertyPath = null)
    {
        if ( ! is_callable($value)) {
            throw new VCRException($message, self::INVALID_CALLABLE, $propertyPath);
        }
    }
}
