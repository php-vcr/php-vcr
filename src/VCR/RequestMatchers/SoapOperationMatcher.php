<?php


namespace VCR\RequestMatchers;


use VCR\Request;

/**
 * Matches if SOAP operation of both specified requests match, or if the request is not a SOAP request.
 */
class SoapOperationMatcher implements RequestMatcherInterface
{

    /**
     * Returns true if the SOAP operation of both specified requests match, or if the request is not a SOAP request.
     *
     * @param  Request $storedRequest First request to match, coming from the cassette.
     * @param  Request $request Second request to match, the request performed by the user.
     *
     * @return boolean True if both specified requests match.
     */
    public function match(Request $storedRequest, Request $request): bool
    {
        $soapOperationRequest = preg_match('/<SOAP-ENV:Body><(.*?)>/m', $request->getBody(), $matches);
        if (empty($matches)) {
            // The request is not a SOAP request
            return true;
        }
        $operationRequest = $matches[1];
        $soapOperationStoredRequest = preg_match('/<SOAP-ENV:Body><(.*?)>/m', $storedRequest->getBody(), $matches);
        if (empty($matches)) {
            // The stored request is not a SOAP request
            return false;
        }
        $operationStoredRequest = $matches[1];

        return $operationRequest === $operationStoredRequest;
    }
}
