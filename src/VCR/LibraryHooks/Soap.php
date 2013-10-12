<?php

namespace VCR\LibraryHooks;

use VCR\Assertion;
use VCR\Configuration;
use VCR\LibraryHooks\LibraryHooksException;
use VCR\Request;
use VCR\Response;
use VCR\Util\StreamProcessor;

/**
 * Library hook for curl functions.
 */
class Soap implements LibraryHookInterface
{
    /**
     * @var Request
     */
    private static $request;
    /**
     * @var Response
     */
    private static $response;
    private static $handleRequestCallback;
    private $status = self::DISABLED;
    /**
     * @var FilterInterface
     */
    private $filter;
    /**
     * @var \VCR\Util\StreamProcessor
     */
    private $processor;

    /**
     * @throws \BadMethodCallException in case the Soap extension is not installed.
     */
    public function __construct(FilterInterface $filter, StreamProcessor $processor)
    {
        if (!class_exists('\SoapClient')) {
            throw new \BadMethodCallException('For soap support you need to install the soap extension.');
        }

        $this->processor = $processor;
        $this->filter = $filter;
    }

    public static function doRequest($request, $location, $action, $version, $one_way = 0)
    {
        if (self::DISABLED) {
            throw new LibraryHooksException(
                'Hook must be enabled.',
                LibraryHooksException::HookDisabled
            );
        }

        $request = new Request('POST', $location);
        $request->addHeader('SoapAction', $action);
        $request->setBody($request);

        $handleRequestCallback = self::$handleRequestCallback;
        $response = $handleRequestCallback($request);
        echo (string)$response->getBody(true);
    }

    /**
     * @inheritDoc
     *
     * @param callable $handleRequestCallback
     */
    public function enable(\Closure $handleRequestCallback)
    {
        Assertion::isCallable($handleRequestCallback, 'No valid callback for handling requests defined.');
        self::$handleRequestCallback = $handleRequestCallback;

        if ($this->status == self::ENABLED) {
            return;
        }

        $this->filter->register();
        $this->processor->appendFilter($this->filter);
        $this->processor->intercept();


        $this->status = self::ENABLED;
    }

    /**
     * @inheritDoc
     * @return null
     */
    public function disable()
    {
        if ($this->status == self::DISABLED) {
            return;
        }

        self::$handleRequestCallback = null;

        $this->status = self::DISABLED;
    }

    public function __destruct()
    {
        self::$handleRequestCallback = null;
    }

}
