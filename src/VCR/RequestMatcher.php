<?php

declare(strict_types=1);

namespace VCR;

class RequestMatcher
{
    public static function matchMethod(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getMethod() == $request->getMethod();
    }

    public static function matchUrl(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getPath() === $request->getPath();
    }

    public static function matchHost(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getHost() === $request->getHost();
    }

    public static function matchHeaders(Request $storedRequest, Request $request): bool
    {
        // Use array_filter to ignore headers which are null.

        return array_filter($storedRequest->getHeaders()) === array_filter($request->getHeaders());
    }

    public static function matchBody(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getBody() === $request->getBody();
    }

    public static function matchPostFields(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getPostFields() === $request->getPostFields();
    }

    public static function matchQueryString(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getQuery() === $request->getQuery();
    }

    public static function matchSoapOperation(Request $storedRequest, Request $request): bool
    {
        if (null === $request->getBody() || null === $storedRequest->getBody()) {
            return true;
        }

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
