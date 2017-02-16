<?php
namespace VCR\Util;

use VCR\Request;
use VCR\Response;

/**
 * Sends requests over the HTTP protocol.
 *
 * This client uses the `curl` handler to send the request, regardless
 * of which LibraryHook is used to capture the user's original request.
 */
class HttpClient
{
    /**
     * Returns a response for specified HTTP request.
     *
     * @param Request $request HTTP Request to send.
     *
     * @return Response Response for specified request.
     *
     * @throws HttpClientException
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

        $response = curl_exec($ch);
        if (false === $response) {
            // The request failed.
            //
            // Throw an exception which a) will allow the CurlHook to fully
            // simulate curl's behaviour to the calling code and b) will at
            // least give a helpful error message for the other LibraryHooks

            $curlInfo = curl_getinfo($ch);
            $curlError = curl_error($ch);
            $curlErrorNo = curl_errno($ch);
            throw new HttpClientException(
                "$curlErrorNo: $curlError",
                $curlInfo,
                $curlError,
                $curlErrorNo);
        }
        list($status, $headers, $body) = HttpUtil::parseResponse($response);

        return new Response(
            HttpUtil::parseStatus($status),
            HttpUtil::parseHeaders($headers),
            $body,
            curl_getinfo($ch)
        );
    }
}
