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
        return $first->getPath() === $second->getPath();
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
        return $first->getHost() === $second->getHost();
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
        // Use array_filter to ignore headers which are null.

        return array_filter($first->getHeaders()) === array_filter($second->getHeaders());
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
        return $first->getBody() === $second->getBody();
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
        return $first->getPostFields() === $second->getPostFields();
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
        return $first->getQuery() === $second->getQuery();
    }
}
