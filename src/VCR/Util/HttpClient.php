<?php
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
     * @param Request $request HTTP Request to send.
     *
     * @return Response Response for specified request.
     */
    public function send(Request $request)
    {
        $ch = curl_init($request->getUrl());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($ch, CURLOPT_HTTPHEADER, HttpUtil::formatHeadersForCurl($request->getHeaders()));
        if (!is_null($request->getBody())) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getBody());
        }

        curl_setopt_array($ch, $request->getCurlOptions());

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_HEADER, true);

        list($status, $headers, $body) = HttpUtil::parseResponse(curl_exec($ch));

        return new Response(
            HttpUtil::parseStatus($status),
            HttpUtil::parseHeaders($headers),
            $body,
            curl_getinfo($ch)
        );
    }
}
