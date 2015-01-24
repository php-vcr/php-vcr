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
     * @var callable
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
     * @param int $one_way
     *
     * @throws \VCR\VCRException It this method is called although VCR is disabled.
     *
     * @return string SOAP response.
     */
    public function doRequest($request, $location, $action, $version, $one_way = 0, $options = array())
    {
        if ($this->status === self::DISABLED) {
            throw new VCRException('Hook must be enabled.', VCRException::LIBRARY_HOOK_DISABLED);
        }

        $vcrRequest = new Request('POST', $location);
        $contentType = ($version == SOAP_1_2) ? 'application/soap+xml' : 'text/xml';
        $vcrRequest->setHeader('Content-Type', $contentType . '; charset=utf-8; action="' . $action . '"');
        $vcrRequest->setBody($request);

        if (isset($options['login'])) {
            $vcrRequest->setAuth($options['login'], $options['password']);
        }
        $requestCallback = self::$requestCallback;
        /**
         * @var \VCR\Response $response
         */
        $response = $requestCallback($vcrRequest);

        return $response->getBody();
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

    /**
     * Cleanup.
     *
     * @return  void
     */
    public function __destruct()
    {
        self::$requestCallback = null;
    }
}
