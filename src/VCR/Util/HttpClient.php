<?php

declare(strict_types=1);

namespace VCR\Util;

use VCR\Request;
use VCR\Response;

/**
 * Sends requests over the HTTP protocol.
 */
class HttpClient
{
    /**
     * Returns a response for specified HTTP request.
     *
     * @throws CurlException In case of cURL error
     */
    public function send(Request $request): Response
    {
        $ch = curl_init($request->getUrl());

        if ($ch === false) {
            Assertion::isResource($ch, "Could not init curl with URL '{$request->getUrl()}'");
        }

        curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($ch, \CURLOPT_HTTPHEADER, HttpUtil::formatHeadersForCurl($request->getHeaders()));
        if (null !== $request->getBody()) {
            curl_setopt($ch, \CURLOPT_POSTFIELDS, $request->getBody());
        }

        curl_setopt_array($ch, $request->getCurlOptions());

        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_FAILONERROR, false);
        curl_setopt($ch, \CURLOPT_HEADER, true);

        /** @var string|false $result */
        $result = curl_exec($ch);
        if (false === $result) {
            throw CurlException::create($ch);
        }
        [$status, $headers, $body] = HttpUtil::parseResponse($result);

        return new Response(
            HttpUtil::parseStatus($status),
            HttpUtil::parseHeaders($headers),
            $body,
            curl_getinfo($ch)
        );
    }
}
