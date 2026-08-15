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

    /**
     * Compares request bodies as JSON documents instead of raw strings.
     * Falls back to matchBody() when either body is not a JSON object or array.
     */
    public static function matchBodyJson(Request $storedRequest, Request $request): bool
    {
        $storedBody = $storedRequest->getBody();
        $body = $request->getBody();

        // Identical bodies are semantically equal by definition. Checking this
        // first keeps the default matcher set — where `body` has already proven
        // the strings equal before this matcher runs — free of any decoding.
        if ($storedBody === $body) {
            return true;
        }

        $storedJson = self::decodeJsonBody($storedBody);
        $json = self::decodeJsonBody($body);

        if (null === $storedJson || null === $json) {
            return self::matchBody($storedRequest, $request);
        }

        return self::jsonValuesMatch($storedJson, $json);
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

    /**
     * Decodes a request body into a JSON object or array.
     * Returns null when the body is empty, invalid JSON or a bare scalar.
     *
     * @return array<mixed>|\stdClass|null
     */
    private static function decodeJsonBody(?string $body): array|\stdClass|null
    {
        if (null === $body) {
            return null;
        }

        // Reject anything that cannot be a JSON object or array before paying for
        // a decode. strspn() walks the leading whitespace without copying the
        // body, which matters when it is a large upload: a multi-megabyte binary
        // or XML body is dismissed on its first byte.
        $offset = strspn($body, " \t\n\r\0\x0B");

        if ($offset === \strlen($body)) {
            return null;
        }

        if ('{' !== $body[$offset] && '[' !== $body[$offset]) {
            return null;
        }

        // Objects are decoded to stdClass on purpose: in associative mode
        // '{"0":"a"}' and '["a"]' decode to the very same PHP value, which
        // would make an object indistinguishable from an array.
        // JSON_BIGINT_AS_STRING keeps integers beyond PHP_INT_MAX exact
        // instead of letting two different integers collapse to one float.
        $decoded = json_decode($body, false, 512, \JSON_BIGINT_AS_STRING);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            return null;
        }

        // The leading-character check above already guarantees this, but
        // json_decode() is typed as mixed and the guard keeps the return type
        // honest without trusting that guarantee.
        if (!\is_array($decoded) && !$decoded instanceof \stdClass) {
            return null;
        }

        return $decoded;
    }

    /**
     * Compares two decoded JSON values: object key order is ignored,
     * array element order is significant and scalars are compared strictly.
     */
    private static function jsonValuesMatch(mixed $storedValue, mixed $value): bool
    {
        if ($storedValue instanceof \stdClass || $value instanceof \stdClass) {
            if (!$storedValue instanceof \stdClass || !$value instanceof \stdClass) {
                return false;
            }

            $storedProperties = get_object_vars($storedValue);
            $properties = get_object_vars($value);

            if (\count($storedProperties) !== \count($properties)) {
                return false;
            }

            foreach ($storedProperties as $key => $storedProperty) {
                if (!\array_key_exists($key, $properties)) {
                    return false;
                }

                if (!self::jsonValuesMatch($storedProperty, $properties[$key])) {
                    return false;
                }
            }

            return true;
        }

        if (\is_array($storedValue) || \is_array($value)) {
            if (!\is_array($storedValue) || !\is_array($value)) {
                return false;
            }

            if (\count($storedValue) !== \count($value)) {
                return false;
            }

            foreach ($storedValue as $index => $storedItem) {
                if (!self::jsonValuesMatch($storedItem, $value[$index])) {
                    return false;
                }
            }

            return true;
        }

        return $storedValue === $value;
    }
}
