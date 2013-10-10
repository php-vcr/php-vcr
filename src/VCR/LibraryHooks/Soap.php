<?php

namespace VCR\LibraryHooks;

use \VCR\Configuration;
use \VCR\Request;
use \VCR\Response;
use \VCR\Assertion;
use VCR\Util\StreamProcessor;

/**
 * Library hook for curl functions.
 */
class Soap implements LibraryHookInterface
{
    private $status = self::DISABLED;

    /**
     * @var Request
     */
    private static $request;

    /**
     * @var Response
     */
    private static $response;

    private static $handleRequestCallback;

    /**
     * @var FilterInterface
     */
    private $filter;

    /**
     * @var \VCR\Util\StreamProcessor
     */
    private $processor;


    /**
     *
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
     *
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

    public static function doRequest($request, $location, $action, $version , $one_way = 0)
    {


        var_dump(__METHOD__, $request, $location, $action, $version, $one_way);


        // $handleRequestCallback = self::$handleRequestCallback;
        // self::$response = $handleRequestCallback(self::$request);
        // echo self::$response->getBody(true);
    }

    public function __destruct()
    {
        self::$handleRequestCallback = null;
    }

}
