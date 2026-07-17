<?php

declare(strict_types=1);

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
     * @param resource $context stream context resource
     */
    public static function createRequestFromStreamContext($context, string $path, ?Request $existing = null): Request
    {
        $http = self::getHttpOptionsFromContext($context);
        $request = $existing;

        if (empty($request)) {
            $method = !empty($http['method']) ? $http['method'] : 'GET';
            $request = new Request($method, $path, []);
        }

        if (!empty($http['header'])) {
            $rawHeaders = \is_array($http['header'])
                ? $http['header']
                : HttpUtil::parseRawHeader($http['header']);
            $headers = HttpUtil::parseHeaders($rawHeaders);
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
            $request->setCurlOption(\CURLOPT_FOLLOWLOCATION, (bool) $http['follow_location']);
        }

        if (isset($http['max_redirects'])) {
            $request->setCurlOption(\CURLOPT_MAXREDIRS, $http['max_redirects']);
        }

        if (isset($http['timeout'])) {
            $request->setCurlOption(\CURLOPT_TIMEOUT, $http['timeout']);
        }

        // TODO: protocol_version

        return $request;
    }

    /**
     * Resolves a possibly-relative Location value against a base URL.
     */
    public static function resolveUrl(string $base, string $location): string
    {
        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'http';

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $authority = $scheme.'://'.$host.$port;

        if (str_starts_with($location, '/')) {
            return $authority.$location;
        }

        $basePath = $parts['path'] ?? '/';
        $lastSlash = strrpos($basePath, '/');
        $dir = false === $lastSlash ? '/' : substr($basePath, 0, $lastSlash + 1);

        return $authority.$dir.$location;
    }

    /**
     * Whether redirects should be followed for the given stream context.
     *
     * Mirrors PHP's native http wrapper default (follow_location = 1).
     *
     * @param resource|null $context
     */
    public static function shouldFollowLocation($context): bool
    {
        $http = self::getHttpOptionsFromContext($context);

        return (bool) ($http['follow_location'] ?? 1);
    }

    /**
     * Maximum number of redirects to follow for the given stream context.
     *
     * Mirrors PHP's native http wrapper default (max_redirects = 20).
     *
     * @param resource|null $context
     */
    public static function maxRedirects($context): int
    {
        $http = self::getHttpOptionsFromContext($context);

        return (int) ($http['max_redirects'] ?? 20);
    }

    /**
     * Returns HTTP options from current stream context.
     *
     * @see http://php.net/manual/en/context.http.php
     *
     * @param resource|null $context
     *
     * @return array<string,mixed> HTTP options
     */
    protected static function getHttpOptionsFromContext($context): array
    {
        if (!$context) {
            return [];
        }

        $options = stream_context_get_options($context);

        return !empty($options['http']) ? $options['http'] : [];
    }
}
