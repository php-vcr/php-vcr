<?php

namespace VCR\Util;

use VCR\Request;

/**
* Stream helper class.
*/
class StreamHelper
{
    /**
     * Returns a Request from specified stream context and path.
     *
     * If an existing Request is given, the stream context options
     * are set on the specified Request object.
     *
     * @param resource $context Stream context resource.
     * @param string $path Path to use as url.
     * @param Request $existing Optional, existing request.
     *
     * @return Request
     */
    public static function createRequestFromStreamContext($context, $path, Request $existing = null)
    {
        $http = self::getHttpOptionsFromContext($context);
        $request = $existing;

        if (empty($request)) {
            $method = !empty($http['method']) ? $http['method'] : 'GET';
            $request = new Request($method, $path, array());
        }

        if (!empty($http['header'])) {
            $headers = HttpUtil::parseHeaders(HttpUtil::parseRawHeader($http['header']));
            foreach ($headers as $key => $value) {
                $request->setHeader($key, $value);
            }
        }

        if (!empty($http['content'])) {
            $request->setBody($http['content']);
        }

        if (!empty($http['user_agent'])) {
            $request->setHeader('User-Agent', $http['user_agent']);
        }

        if (isset($http['follow_location'])) {
            $request->setCurlOption(CURLOPT_FOLLOWLOCATION, (bool) $http['follow_location']);
        }

        if (isset($http['max_redirects'])) {
            $request->setCurlOption(CURLOPT_MAXREDIRS, $http['max_redirects']);
        }

        if (isset($http['timeout'])) {
            $request->setCurlOption(CURLOPT_TIMEOUT, $http['timeout']);
        }

        // TODO: protocol_version

        return $request;
    }

    /**
     * Returns HTTP options from current stream context.
     *
     * @see http://php.net/manual/en/context.http.php
     * @return array HTTP options.
     */
    protected static function getHttpOptionsFromContext($context)
    {
        if (!$context) {
            return array();
        }

        $options = stream_context_get_options($context);

        return !empty($options['http']) ? $options['http'] : array();
    }
}
