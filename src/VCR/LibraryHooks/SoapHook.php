<?php

declare(strict_types=1);

namespace VCR\LibraryHooks;

use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Request;
use VCR\Util\Assertion;
use VCR\Util\StreamProcessor;
use VCR\VCRException;

class SoapHook implements LibraryHook
{
    private static ?\Closure $requestCallback;

    private string $status = self::DISABLED;

    /**
     * @throws \BadMethodCallException in case the Soap extension is not installed
     */
    public function __construct(
        private AbstractCodeTransform $codeTransformer,
        private StreamProcessor $processor
    ) {
        if (!class_exists('\SoapClient')) {
            throw new \BadMethodCallException('For soap support you need to install the soap extension.');
        }

        if (!class_exists('\DOMDocument')) {
            throw new \BadMethodCallException('For soap support you need to install the xml extension.');
        }
    }

    /**
     * @param array<string,mixed> $options
     */
    public function doRequest(string $request, string $location, string $action, int $version, bool $one_way = false, array $options = []): string
    {
        if (self::DISABLED === $this->status) {
            throw new VCRException('Hook must be enabled.', VCRException::LIBRARY_HOOK_DISABLED);
        }

        $vcrRequest = new Request('POST', $location);

        if (\SOAP_1_1 === $version) {
            $vcrRequest->setHeader('Content-Type', 'text/xml; charset=utf-8');
            $vcrRequest->setHeader('SOAPAction', $action);
        } else { // >= SOAP_1_2
            $vcrRequest->setHeader(
                'Content-Type',
                \sprintf('application/soap+xml; charset=utf-8; action="%s"', $action)
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

    public function enable(\Closure $requestCallback): void
    {
        self::$requestCallback = $requestCallback;

        if (self::ENABLED == $this->status) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        $this->status = self::ENABLED;
    }

    public function disable(): void
    {
        self::$requestCallback = null;

        $this->status = self::DISABLED;
    }

    public function isEnabled(): bool
    {
        return self::ENABLED == $this->status;
    }

    public function __destruct()
    {
        self::$requestCallback = null;
    }
}
