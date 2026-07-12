<?php

declare(strict_types=1);

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
            [$key, $value] = explode(': ', $line, 2);
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
        // Pre-check with str_starts_with so that Assertion::startsWith is only
        // called on the error path. On PHP 8.4, beberlei/assert <3.3.4 calls
        // mb_strpos() with null offset inside startsWith(), triggering E_DEPRECATED.
        // Symfony NativeResponse registers its own error handler during stream reads
        // that converts any E_DEPRECATED into a TransportException — so the deprecation
        // must never be triggered on the happy path. Keeping Assertion::startsWith on
        // the error path preserves the Assert\InvalidArgumentException type for callers.
        if (!str_starts_with($status, 'HTTP/')) {
            Assertion::startsWith(
                $status,
                'HTTP/',
                "Invalid HTTP status '$status', expected format like: 'HTTP/1.1 200 OK'."
            );
        }

        $part = explode(' ', $status, 3);

        return [
            'http_version' => substr(strrchr($part[0], '/') ?? '', 1),
            'code' => $part[1],
            'message' => $part[2] ?? '',
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
        $responseOffset = 0;
        $separatorOffset = strpos($response, "\r\n\r\n");
        while (false !== $separatorOffset) {
            $rawHeader = substr($response, $responseOffset, $separatorOffset - $responseOffset);
            $headerLines = explode("\r\n", $rawHeader);
            $statusLine = array_shift($headerLines);
            if (1 !== preg_match('/^HTTP\/\S+ (\d{3})(?: [^\r\n]*)?$/D', $statusLine, $matches)) {
                break;
            }

            $statusCode = (int) $matches[1];
            $isInformational = 100 <= $statusCode && 200 > $statusCode;
            $isProxyAcknowledgement = 200 <= $statusCode && 300 > $statusCode && [] === $headerLines;
            if (!$isInformational && !$isProxyAcknowledgement) {
                break;
            }

            $nextResponseOffset = $separatorOffset + 4;
            if ($nextResponseOffset !== strpos($response, 'HTTP/', $nextResponseOffset)) {
                break;
            }

            $responseOffset = $nextResponseOffset;
            $separatorOffset = strpos($response, "\r\n\r\n", $responseOffset);
        }

        [$rawHeader, $rawBody] = explode("\r\n\r\n", substr($response, $responseOffset), 2);

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
     * @param array<string,string|array<string,string>|null> $headers Headers as key/value pairs
     *
     * @return string[] List of headers ['Content-Type: text/html\r\n', '...'].
     */
    public static function formatHeadersForCurl(array $headers): array
    {
        $curlHeaders = [];

        foreach ($headers as $key => $values) {
            if (\is_array($values)) {
                foreach ($values as $value) {
                    $curlHeaders[] = $key.': '.$value."\r\n";
                }
            } else {
                $curlHeaders[] = $key.': '.$values."\r\n";
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
        return 'HTTP/'.($response->getHttpVersion() ?? '1.1')
            .' '.$response->getStatusCode()
            .' '.$response->getStatusMessage()
            ."\r\n";
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

        return implode('', $headers)."\r\n";
    }
}
