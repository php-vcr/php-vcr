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
     * @param  Request $first  First request to match.
     * @param  Request $second Second request to match.
     *
     * @return boolean True if the method of both specified requests match.
     */
    public static function matchMethod(Request $first, Request $second)
    {
        return $first->getMethod() == $second->getMethod();
    }

    /**
     * Returns true if the url of both specified requests match.
     *
     * @param  Request $first  First request to match.
     * @param  Request $second Second request to match.
     *
     * @return boolean True if the url of both specified requests match.
     */
    public static function matchUrl(Request $first, Request $second)
    {
        return !((null !== $first->getPath()) and ((string) $first->getPath() != (string) $second->getPath()));
    }

    /**
     * Returns true if the host of both specified requests match.
     *
     * @param  Request $first  First request to match.
     * @param  Request $second Second request to match.
     *
     * @return boolean True if the host of both specified requests match.
     */
    public static function matchHost(Request $first, Request $second)
    {
        if (null !== $first->getHost()
           && !preg_match('#'.str_replace('#', '\\#', $first->getHost()).'#i', $second->getHost())) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the headers of both specified requests match.
     *
     * @param  Request $first  First request to match.
     * @param  Request $second Second request to match.
     *
     * @return boolean True if the headers of both specified requests match.
     */
    public static function matchHeaders(Request $first, Request $second)
    {
        $firstHeaders = $first->getHeaders();
        foreach ($second->getHeaders() as $key => $pattern) {
            if (!array_key_exists($key, $firstHeaders)
                || $pattern !== $firstHeaders[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if the body of both specified requests match.
     *
     * @param  Request $first  First request to match.
     * @param  Request $second Second request to match.
     *
     * @return boolean True if the body of both specified requests match.
     */
    public static function matchBody(Request $first, Request $second)
    {
        if (null !== $first->getBody() && (string) $first->getBody() != (string) $second->getBody() ) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the post fields of both specified requests match.
     *
     * @param  Request $first  First request to match.
     * @param  Request $second Second request to match.
     *
     * @return boolean True if the post fields of both specified requests match.
     */
    public static function matchPostFields(Request $first, Request $second)
    {
        if (null !== $first->getPostFields() && $first->getPostFields() != $second->getPostFields()) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the query string of both specified requests match.
     *
     * @param  Request $first  First request to match.
     * @param  Request $second Second request to match.
     *
     * @return boolean True if the query string of both specified requests match.
     */
    public static function matchQueryString(Request $first, Request $second)
    {
        if (null !== $first->getQuery() && $first->getQuery() != $second->getQuery()) {
            return false;
        }
        return true;
    }
}
