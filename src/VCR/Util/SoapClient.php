<?php

namespace VCR\Util;

use VCR\LibraryHooks\SoapHook;
use VCR\VCRFactory;

/**
 * SoapClient replaces PHPs \SoapClient to allow interception.
 */
class SoapClient extends \SoapClient
{
    /**
     * @var \VCR\LibraryHooks\SoapHook SOAP library hook used to intercept SOAP requests
     */
    protected $soapHook;

    /**
     * @var array<string,mixed>
     */
    protected $options = [];

    /**
     * @var string
     */
    protected $response;

    /**
     * @var string
     */
    protected $request;

    /**
     * @param mixed               $wsdl
     * @param array<string,mixed> $options
     */
    public function __construct($wsdl, array $options = [])
    {
        $this->options = $options;
        parent::__construct($wsdl, $options);
    }

    /**
     * Performs (and may intercepts) SOAP request over HTTP.
     *
     * Requests will be intercepted if the library hook is enabled.
     *
     * @param string $request  the XML SOAP request
     * @param string $location the URL to request
     * @param string $action   the SOAP action
     * @param int    $version  the SOAP version
     * @param int    $one_way  If one_way is set to 1, this method returns nothing.
     *                         Use this where a response is not expected.
     *
     * @return string|null the XML SOAP response (or null if $one_way is set)
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        // Save a copy of the request, not the request itself -- see issue #153
        $this->request = (string) $request;

        $soapHook = $this->getLibraryHook();

        if ($soapHook->isEnabled()) {
            $response = $soapHook->doRequest($request, $location, $action, $version, $one_way, $this->options);
        } else {
            $response = $this->realDoRequest($request, $location, $action, $version, $one_way);
        }

        $this->response = $response;

        return $one_way ? null : $response;
    }

    /**
     * {@inheritdoc}
     */
    public function __getLastRequest(): ?string
    {
        return $this->request;
    }

    /**
     * {@inheritdoc}
     */
    public function __getLastResponse(): ?string
    {
        return $this->response;
    }

    /**
     * Sets the SOAP library hook which is used to intercept SOAP requests.
     *
     * @param soapHook $hook SOAP library hook to use when intercepting SOAP requests
     */
    public function setLibraryHook(SoapHook $hook): void
    {
        $this->soapHook = $hook;
    }

    /**
     * Performs a real SOAP request over HTTP.
     *
     * @codeCoverageIgnore
     *
     * @param string $request  the XML SOAP request
     * @param string $location the URL to request
     * @param string $action   the SOAP action
     * @param int    $version  the SOAP version
     * @param int    $one_way  If one_way is set to 1, this method returns nothing.
     *                         Use this where a response is not expected.
     *
     * @return string the XML SOAP response
     */
    protected function realDoRequest(string $request, string $location, string $action, int $version, int $one_way = 0): string
    {
        return parent::__doRequest($request, $location, $action, $version, $one_way);
    }

    /**
     * Returns currently used SOAP library hook.
     *
     * If no library hook is set, a new one is created.
     *
     * @return soapHook SOAP library hook
     */
    protected function getLibraryHook(): SoapHook
    {
        if (empty($this->soapHook)) {
            $this->soapHook = VCRFactory::get('VCR\LibraryHooks\SoapHook');
        }

        return $this->soapHook;
    }
}
