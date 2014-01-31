<?php

namespace VCR\Util\Soap;

use VCR\LibraryHooks\LibraryHookInterface;
use VCR\VCRException;
use VCR\VCRFactory;

class SoapClient extends \SoapClient
{
    /**
     * @var \VCR\LibraryHooks\LibraryHookInterface;
     */
    protected $soapHook;

    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $soap = $this->getLibraryHook();

        try {
            $response = $soap->doRequest($request, $location, $action, $version, $one_way);
        } catch (VCRException $e) {
            if (VCRException::LIBRARY_HOOK_DISABLED === $e->getCode()) {
                $response = parent::__doRequest($request, $location, $action, $version, $one_way);
            } else {
                throw $e;
            }
        }

        return $response;
    }

    public function setLibraryHook(LibraryHookInterface $hook )
    {
        $this->soapHook = $hook;
    }

    public function getLibraryHook()
    {
        if (empty($this->soapHook)) {
            $this->soapHook = VCRFactory::get('VCR\\LibraryHooks\\Soap');
        }

        return $this->soapHook;
    }
}
