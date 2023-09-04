<?php

declare(strict_types=1);

namespace VCR\Util;

use VCR\LibraryHooks\SoapHook;
use VCR\VCRFactory;

/**
 * SoapClient replaces PHPs \SoapClient to allow interception.
 */
class SoapClient extends \SoapClient
{
    protected SoapHook $soapHook;

    /**
     * @var array<string,mixed>
     */
    protected array $options = [];

    protected string $response;

    protected string $request;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(?string $wsdl, array $options = [])
    {
        $this->options = $options;
        parent::__construct($wsdl, $options);
    }

    /**
     * Performs (and may intercepts) SOAP request over HTTP.
     *
     * Requests will be intercepted if the library hook is enabled.
     */
    public function __doRequest(string $request, string $location, string $action, int $version, bool $one_way = false): ?string
    {
        $this->request = $request;

        $soapHook = $this->getLibraryHook();

        if ($soapHook->isEnabled()) {
            $this->response = $soapHook->doRequest($request, $location, $action, $version, $one_way, $this->options);
        } else {
            $this->response = $this->realDoRequest($request, $location, $action, $version, $one_way);
        }

        return $one_way ? null : $this->response;
    }

    public function __getLastRequest(): ?string
    {
        return $this->request ?? null;
    }

    public function __getLastResponse(): ?string
    {
        return $this->response ?? null;
    }

    public function setLibraryHook(SoapHook $hook): void
    {
        $this->soapHook = $hook;
    }

    protected function realDoRequest(string $request, string $location, string $action, int $version, bool $one_way = false): string
    {
        return parent::__doRequest($request, $location, $action, $version, $one_way);
    }

    protected function getLibraryHook(): SoapHook
    {
        if (!isset($this->soapHook)) {
            $this->soapHook = VCRFactory::get('VCR\LibraryHooks\SoapHook');
        }

        return $this->soapHook;
    }
}
