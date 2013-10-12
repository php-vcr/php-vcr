<?php

namespace VCR\Util\Soap;


use VCR\Request;
use VCR\VCRFactory;
use VCR\LibraryHooks\LibraryHooksException;

class SoapClient extends \SoapClient
{

    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $response = '';
        $soap = VCRFactory::get('VCR\\LibraryHooks\\Soap');

        try {
            $response = $soap->doRequest($request, $location, $action, $version, $one_way = 0);

        } catch (LibraryHooksException $e) {
            // libraryHook disabled

            if (LibraryHooksException::HookDisabled === $e->getCode()) {

                $response = parent::__doRequest($request, $location, $action, $version, $one_way);
            } else {

                throw $e;
            }
        }

        return $response;
    }
}
