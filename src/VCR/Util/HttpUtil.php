<?php

namespace VCR\Util;

use VCR\Response;

class HttpUtil
{
    /**
     * Returns key value pairs of response headers.
     *
     * @param array $headers List of headers. Example: ['Content-Type: text/html', '...']
     * @return array Key/value pairs of headers, e.g. ['Content-Type' => 'text/html']
     */
    public static function parseHeaders(array $headers)
    {
        $headerGroups = array();
        $headerList = array();

        // Collect matching headers into groups
        foreach ($headers as $line) {
            list ($key, $value) = explode(': ', $line);
            if (!isset($headerGroups[$key])) {
                $headerGroups[$key] = array();
            }
            $headerGroups[$key][] = $value;
        }
        
        // Collapse groups
        foreach ($headerGroups as $key => $values) {
            $headerList[$key] = implode(', ', $values);
        }

        return $headerList;
    }

    /**
     * Returns http_version, code and message from a HTTP status line.
     *
     * @param string $status HTTP status line, e.g. HTTP/1.1 200 OK
     * @return array Parsed 'http_version', 'code' and 'message'.
     */
    public static function parseStatus($status)
    {
        Assertion::startsWith($status, 'HTTP/', "Invalid HTTP status '$status', expected format like: 'HTTP/1.1 200 OK'.");

        $part = explode(' ', $status, 3);

        return array(
            'http_version' => substr(strrchr($part[0], '/'), 1),
            'code' => $part[1],
            'message' => isset($part[2]) ? $part[2] : ''
        );
    }

    /**
     * Returns status, headers and body from a HTTP response string.
     *
     * @param string $response Response including header and body.
     * @return array Status, headers and body as strings.
     */
    public static function parseResponse($response)
    {
        $response = str_replace("HTTP/1.1 100 Continue\r\n\r\n", '', $response);
            
        list($rawHeader, $rawBody) = explode("\r\n\r\n", $response, 2);

        // Parse headers and status.
        $headers = self::parseRawHeader($rawHeader);
        $status = array_shift($headers);

        return array($status, $headers, $rawBody);
    }

    /**
     * Returns an array of arrays for specified raw header string.
     *
     * @param string $header
     * @return array
     */
    public static function parseRawHeader($rawHeader) {
        return explode("\r\n", trim($rawHeader));
    }

    /**
     * Returns a list of headers from a key/value paired array.
     *
     * @param array $headers Headers as key/value pairs.
     * @return array List of headers ['Content-Type: text/html', '...'].
     */
    public static function formatHeadersForCurl(array $headers)
    {
        $curlHeaders = array();

        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }

        return $curlHeaders;
    }

    /**
     * Returns a HTTP status line from specified response.
     *
     * @param Response $response
     * @return string HTTP status line.
     */
    public static function formatAsStatusString(Response $response)
    {
        return 'HTTP/' . $response->getHttpVersion()
             . ' ' . $response->getStatusCode()
             . ' ' . $response->getStatusMessage();
    }

    /**
     * Returns a HTTP status line with headers from specified response.
     *
     * @param Response $response
     * @return string HTTP status line.
     */
    public static function formatAsStatusWithHeadersString(Response $response)
    {
        $headers = self::formatHeadersForCurl($response->getHeaders());
        array_unshift($headers, self::formatAsStatusString($response));
        return join("\r\n", $headers) . "\r\n\r\n";
    }
}
