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
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getBody());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request->getHeaders());

        curl_setopt_array($ch, $request->getCurlOptions());

        $response = curl_exec($ch);
        if ($response === false) {
            syslog(LOG_WARNING, "PHP-VCR cURL failed: " . curl_error($ch));
        }

        return $this->responseFromCurlResponse($ch, $response);
    }

    /**
     * @param resource $ch
     * @param string $response Response including header and body.
     * @return Response
     */
    protected function responseFromCurlResponse($ch, $response)
    {
        list($header, $body) = explode("\r\n\r\n", $response, 2);

        return new Response(
            curl_getinfo($ch, CURLINFO_HTTP_CODE),
            $this->parseHeaders($header),
            $body,
            curl_getinfo($ch)
        );
    }

    /**
     * Returns key value pairs of response headers.
     *
     * @param string $header
     * @return array Key/value pairs of headers.
     */
    protected function parseHeaders($header)
    {
        $headers = array();

        foreach (explode("\r\n", $header) as $i => $line) {
            // skip status
            if ($i === 0) {
                continue;
            }

            list ($key, $value) = explode(': ', $line);
            $headers[$key] = $value;
        }

        return $headers;
    }
}
