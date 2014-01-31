<?php

namespace VCR\LibraryHooks;

use VCR\Assertion;
use VCR\VCRException;
use VCR\Request;
use VCR\Util\StreamProcessor;

/**
 * Library hook for curl functions.
 */
class Soap implements LibraryHookInterface
{
    /**
     * @var string
     */
    private static $handleRequestCallback;
    /**
     * @var string
     */
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
     * @param FilterInterface $filter
     * @param StreamProcessor $processor
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

    public function doRequest($request, $location, $action, $version, $one_way = 0)
    {
        if ($this->status === self::DISABLED) {
            throw new VCRException('Hook must be enabled.', VCRException::LIBRARY_HOOK_DISABLED);
        }

        $vcrRequest = new Request('POST', $location);
        $contentType = ($version == SOAP_1_2) ? 'application/soap+xml' : 'text/xml';
        $vcrRequest->addHeader('Content-Type', $contentType . '; charset=utf-8; action="' . $action . '"');
        $vcrRequest->setBody($request);

        $handleRequestCallback = self::$handleRequestCallback;
        $response = $handleRequestCallback($vcrRequest);

        return (string) $response->getBody(true);
    }

    /**
     * @inheritDoc
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
