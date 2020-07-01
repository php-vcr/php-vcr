<?php

namespace VCR\LibraryHooks;

use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Request;
use VCR\Util\Assertion;
use VCR\Util\StreamProcessor;
use VCR\VCRException;

/**
 * Library hook for curl functions.
 */
class SoapHook implements LibraryHook
{
    /**
     * @var callable|null
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
     * @throws \BadMethodCallException in case the Soap extension is not installed
     */
    public function __construct(AbstractCodeTransform $codeTransformer, StreamProcessor $processor)
    {
        if (!class_exists('\SoapClient')) {
            throw new \BadMethodCallException('For soap support you need to install the soap extension.');
        }

        if (!class_exists('\DOMDocument')) {
            throw new \BadMethodCallException('For soap support you need to install the xml extension.');
        }

        $this->processor = $processor;
        $this->codeTransformer = $codeTransformer;
    }

    /**
     * @param array<string,mixed> $options
     *
     * @return string SOAP response
     */
    public function doRequest(string $request, string $location, string $action, int $version, int $one_way = 0, array $options = []): string
    {
        if (self::DISABLED === $this->status) {
            throw new VCRException('Hook must be enabled.', VCRException::LIBRARY_HOOK_DISABLED);
        }

        $vcrRequest = new Request('POST', $location);

        if (SOAP_1_1 === $version) {
            $vcrRequest->setHeader('Content-Type', 'text/xml; charset=utf-8;');
            $vcrRequest->setHeader('SOAPAction', $action);
        } else { // >= SOAP_1_2
            $vcrRequest->setHeader(
                'Content-Type',
                sprintf('application/soap+xml; charset=utf-8; action="%s"', $action)
            );
        }

        $vcrRequest->setBody($request);

        if (!empty($options['login'])) {
            $vcrRequest->setAuthorization($options['login'], $options['password']);
        }

        /* @var \VCR\Response $response */
        $requestCallback = self::$requestCallback;
        Assertion::isCallable($requestCallback);
        $response = $requestCallback($vcrRequest);

        return $response->getBody();
    }

    /**
     * {@inheritdoc}
     */
    public function enable(\Closure $requestCallback): void
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');
        self::$requestCallback = $requestCallback;

        if (self::ENABLED == $this->status) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        $this->status = self::ENABLED;
    }

    /**
     * {@inheritdoc}
     */
    public function disable(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        self::$requestCallback = null;

        $this->status = self::DISABLED;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return self::ENABLED == $this->status;
    }

    /**
     * Cleanup.
     *
     * @return void
     */
    public function __destruct()
    {
        self::$requestCallback = null;
    }
}
