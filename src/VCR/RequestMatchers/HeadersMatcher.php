<?php


namespace VCR\RequestMatchers;


use VCR\Request;

class HeadersMatcher implements RequestMatcherInterface
{

    /**
     * Returns true if both requests match.
     *
     * @param  Request $storedRequest First request to match, coming from the cassette.
     * @param  Request $request Second request to match, the request performed by the user.
     *
     * @return boolean True if both specified requests match.
     */
    public function match(Request $storedRequest, Request $request): bool
    {
        // Use array_filter to ignore headers which are null.

        return array_filter($storedRequest->getHeaders()) === array_filter($request->getHeaders());
    }
}
