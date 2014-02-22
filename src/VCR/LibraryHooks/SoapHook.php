<?php

namespace VCR\LibraryHooks;

use VCR\Util\Assertion;
use VCR\VCRException;
use VCR\Request;
use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Util\StreamProcessor;

/**
 * Library hook for curl functions.
 */
class SoapHook implements LibraryHook
{
    /**
     * @var string
     */
    private static $requestCallback;
    /**
     * @var string
     */
    private $status = self::DISABLED;
    /**
     * @var AbstractCodeTransform
     */
    private $codeTransformer;
    /**
     * @var \VCR\Util\StreamProcessor
     */
    private $processor;

    /**
     * Creates a SOAP hook instance.
     *
     * @param AbstractCodeTransform  $codeTransformer
     * @param StreamProcessor $processor
     *
     * @throws \BadMethodCallException in case the Soap extension is not installed.
     */
    public function __construct(AbstractCodeTransform $codeTransformer, StreamProcessor $processor)
    {
        if (!class_exists('\SoapClient')) {
            throw new \BadMethodCallException('For soap support you need to install the soap extension.');
        }

        $this->processor = $processor;
        $this->codeTransformer = $codeTransformer;
    }

    /**
     * @param string $request
     * @param string $location
     * @param string $action
     * @param integer $version
     */
    public function doRequest($request, $location, $action, $version, $one_way = 0)
    {
        if ($this->status === self::DISABLED) {
            throw new VCRException('Hook must be enabled.', VCRException::LIBRARY_HOOK_DISABLED);
        }

        $vcrRequest = new Request('POST', $location);
        $contentType = ($version == SOAP_1_2) ? 'application/soap+xml' : 'text/xml';
        $vcrRequest->addHeader('Content-Type', $contentType . '; charset=utf-8; action="' . $action . '"');
        $vcrRequest->setBody($request);

        $requestCallback = self::$requestCallback;
        $response = $requestCallback($vcrRequest);

        return (string) $response->getBody(true);
    }

    /**
     * @inheritDoc
     */
    public function enable(\Closure $requestCallback)
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');
        self::$requestCallback = $requestCallback;

        if ($this->status == self::ENABLED) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        $this->status = self::ENABLED;
    }

    /**
     * @inheritDoc
     */
    public function disable()
    {
        if (!$this->isEnabled()) {
            return;
        }

        self::$requestCallback = null;

        $this->status = self::DISABLED;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled()
    {
        return $this->status == self::ENABLED;
    }

    public function __destruct()
    {
        self::$requestCallback = null;
    }

}
