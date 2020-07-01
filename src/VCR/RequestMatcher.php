<?php

namespace VCR;

/**
 * Collection of matcher methods to match two requests.
 */
class RequestMatcher
{
    /**
     * Returns true if the method of both specified requests match.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the method of both specified requests match
     */
    public static function matchMethod(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getMethod() == $request->getMethod();
    }

    /**
     * Returns true if the url of both specified requests match.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the url of both specified requests match
     */
    public static function matchUrl(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getPath() === $request->getPath();
    }

    /**
     * Returns true if the host of both specified requests match.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the host of both specified requests match
     */
    public static function matchHost(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getHost() === $request->getHost();
    }

    /**
     * Returns true if the headers of both specified requests match.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the headers of both specified requests match
     */
    public static function matchHeaders(Request $storedRequest, Request $request): bool
    {
        // Use array_filter to ignore headers which are null.

        return array_filter($storedRequest->getHeaders()) === array_filter($request->getHeaders());
    }

    /**
     * Returns true if the body of both specified requests match.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the body of both specified requests match
     */
    public static function matchBody(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getBody() === $request->getBody();
    }

    /**
     * Returns true if the post fields of both specified requests match.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the post fields of both specified requests match
     */
    public static function matchPostFields(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getPostFields() === $request->getPostFields();
    }

    /**
     * Returns true if the query string of both specified requests match.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the query string of both specified requests match
     */
    public static function matchQueryString(Request $storedRequest, Request $request): bool
    {
        return $storedRequest->getQuery() === $request->getQuery();
    }

    /**
     * Returns true if the SOAP operation of both specified requests match, or if the request is not a SOAP request.
     *
     * @param Request $storedRequest first request to match, coming from the cassette
     * @param Request $request       second request to match, the request performed by the user
     *
     * @return bool true if the query string of both specified requests match
     */
    public static function matchSoapOperation(Request $storedRequest, Request $request): bool
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
