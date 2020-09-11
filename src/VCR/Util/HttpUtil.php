<?php

namespace VCR\Util;

use VCR\Response;

class HttpUtil
{
    /**
     * Returns key value pairs of response headers.
     *
     * @param string[] $headers List of headers. Example: ['Content-Type: text/html', '...']
     *
     * @return array<string,string> Key/value pairs of headers, e.g. ['Content-Type' => 'text/html']
     */
    public static function parseHeaders(array $headers): array
    {
        // Collect matching headers into groups
        foreach ($headers as $i => $line) {
            list($key, $value) = explode(': ', $line, 2);
            if (isset($headers[$key])) {
                if (\is_array($headers[$key])) {
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = [$headers[$key], $value];
                }
            } else {
                $headers[$key] = $value;
            }
            unset($headers[$i]);
        }

        return $headers;
    }

    /**
     * Returns http_version, code and message from a HTTP status line.
     *
     * @param string $status HTTP status line, e.g. HTTP/1.1 200 OK
     *
     * @return array<string,string> parsed 'http_version', 'code' and 'message'
     */
    public static function parseStatus(string $status): array
    {
        Assertion::startsWith(
            $status,
            'HTTP/',
            "Invalid HTTP status '$status', expected format like: 'HTTP/1.1 200 OK'."
        );

        $part = explode(' ', $status, 3);

        return [
            'http_version' => substr(strrchr($part[0], '/'), 1),
            'code' => $part[1],
            'message' => isset($part[2]) ? $part[2] : '',
        ];
    }

    /**
     * Returns status, headers and body from a HTTP response string.
     *
     * @param string $response response including header and body
     *
     * @return array<int,mixed> status, headers and body as strings
     */
    public static function parseResponse(string $response): array
    {
        $response = str_replace("HTTP/1.1 100 Continue\r\n\r\n", '', $response);

        list($rawHeader, $rawBody) = explode("\r\n\r\n", $response, 2);

        // Parse headers and status.
        $headers = self::parseRawHeader($rawHeader);
        $status = array_shift($headers);

        return [$status, $headers, $rawBody];
    }

    /**
     * Returns an array of arrays for specified raw header string.
     *
     * @return array<int,string>
     */
    public static function parseRawHeader(string $rawHeader): array
    {
        return explode("\r\n", trim($rawHeader));
    }

    /**
     * Returns a list of headers from a key/value paired array.
     *
     * @param array<string,string|array<string,string>> $headers Headers as key/value pairs
     *
     * @return string[] List of headers ['Content-Type: text/html', '...'].
     */
    public static function formatHeadersForCurl(array $headers): array
    {
        $curlHeaders = [];

        foreach ($headers as $key => $values) {
            if (\is_array($values)) {
                foreach ($values as $value) {
                    $curlHeaders[] = $key.': '.$value;
                }
            } else {
                $curlHeaders[] = $key.': '.$values;
            }
        }

        return $curlHeaders;
    }

    /**
     * Returns a HTTP status line from specified response.
     *
     * @return string HTTP status line
     */
    public static function formatAsStatusString(Response $response): string
    {
        return 'HTTP/'.$response->getHttpVersion()
             .' '.$response->getStatusCode()
             .' '.$response->getStatusMessage();
    }

    /**
     * Returns a HTTP status line with headers from specified response.
     *
     * @return string HTTP status line
     */
    public static function formatAsStatusWithHeadersString(Response $response): string
    {
        $headers = self::formatHeadersForCurl($response->getHeaders());
        array_unshift($headers, self::formatAsStatusString($response));

        return implode("\r\n", $headers)."\r\n\r\n";
    }
}
