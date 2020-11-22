<?php

namespace VCR\Util;

use VCR\Request;
use VCR\Response;

/**
 * cURL helper class.
 */
class CurlHelper
{
    /**
     * @var array<int, string> list of cURL info constants
     */
    private static $curlInfoList = [
        //"certinfo"?
        CURLINFO_HTTP_CODE => 'http_code',
        CURLINFO_EFFECTIVE_URL => 'url',
        CURLINFO_TOTAL_TIME => 'total_time',
        CURLINFO_NAMELOOKUP_TIME => 'namelookup_time',
        CURLINFO_CONNECT_TIME => 'connect_time',
        CURLINFO_PRETRANSFER_TIME => 'pretransfer_time',
        CURLINFO_STARTTRANSFER_TIME => 'starttransfer_time',
        CURLINFO_REDIRECT_COUNT => 'redirect_count',
        CURLINFO_REDIRECT_TIME => 'redirect_time',
        CURLINFO_SIZE_UPLOAD => 'size_upload',
        CURLINFO_SIZE_DOWNLOAD => 'size_download',
        CURLINFO_SPEED_DOWNLOAD => 'speed_download',
        CURLINFO_SPEED_UPLOAD => 'speed_upload',
        CURLINFO_HEADER_SIZE => 'header_size',
        CURLINFO_HEADER_OUT => 'request_header',
        CURLINFO_FILETIME => 'filetime',
        CURLINFO_REQUEST_SIZE => 'request_size',
        CURLINFO_SSL_VERIFYRESULT => 'ssl_verify_result',
        CURLINFO_CONTENT_LENGTH_DOWNLOAD => 'download_content_length',
        CURLINFO_CONTENT_LENGTH_UPLOAD => 'upload_content_length',
        CURLINFO_CONTENT_TYPE => 'content_type',
    ];

    /**
     * Outputs a response depending on the set cURL option.
     *
     * The response body can be written to a file, returned, echoed or
     * passed to a custom function.
     *
     * The response header might be passed to a custom function.
     *
     * @param Response          $response    response which contains the response body
     * @param array<int, mixed> $curlOptions cURL options which are not stored within the Response
     * @param resource          $ch          cURL handle to add headers if needed
     */
    public static function handleOutput(Response $response, array $curlOptions, $ch): ?string
    {
        // If there is a header function set, feed the http status and headers to it.
        if (isset($curlOptions[CURLOPT_HEADERFUNCTION])) {
            $headerList = [HttpUtil::formatAsStatusString($response)];
            $headerList = array_merge($headerList, HttpUtil::formatHeadersForCurl($response->getHeaders()));
            $headerList[] = '';
            foreach ($headerList as $header) {
                self::callFunction($curlOptions[CURLOPT_HEADERFUNCTION], $ch, $header);
            }
        }

        $body = $response->getBody();

        if (!empty($curlOptions[CURLOPT_HEADER])) {
            $body = HttpUtil::formatAsStatusWithHeadersString($response).$body;
        }

        if (isset($curlOptions[CURLOPT_WRITEFUNCTION])) {
            self::callFunction($curlOptions[CURLOPT_WRITEFUNCTION], $ch, $body);
        } elseif (isset($curlOptions[CURLOPT_RETURNTRANSFER]) && true == $curlOptions[CURLOPT_RETURNTRANSFER]) {
            return $body;
        } elseif (isset($curlOptions[CURLOPT_FILE])) {
            $fp = $curlOptions[CURLOPT_FILE];
            fwrite($fp, $body);
            fflush($fp);
        } else {
            echo $body;
        }

        return null;
    }

    /**
     * Returns a cURL option from a Response.
     *
     * @param Response $response response to get cURL option from
     * @param int      $option   cURL option to get
     *
     * @throws \BadMethodCallException
     *
     * @return mixed value of the cURL option
     */
    public static function getCurlOptionFromResponse(Response $response, int $option = 0)
    {
        switch ($option) {
            case 0: // 0 == array of all curl options
                $info = [];
                foreach (self::$curlInfoList as $option => $key) {
                    $info[$key] = $response->getCurlInfo($key);
                }
                break;
            case CURLINFO_HTTP_CODE:
                $info = (int) $response->getStatusCode();
                break;
            case CURLINFO_SIZE_DOWNLOAD:
                $info = $response->getHeader('Content-Length');
                break;
            case CURLINFO_HEADER_SIZE:
                $info = mb_strlen(HttpUtil::formatAsStatusWithHeadersString($response), 'ISO-8859-1');
                break;
            default:
                $info = $response->getCurlInfo(self::$curlInfoList[$option]);
                break;
        }

        if (null !== $info) {
            return $info;
        }

        $constants = get_defined_constants(true);
        $constantNames = array_flip($constants['curl']);
        throw new \BadMethodCallException("Not implemented: {$constantNames[$option]} ({$option}) ");
    }

    /**
     * Sets a cURL option on a Request.
     *
     * @param Request  $request    request to set cURL option to
     * @param int      $option     cURL option to set
     * @param mixed    $value      value of the cURL option
     * @param resource $curlHandle cURL handle where this option is set on (optional)
     */
    public static function setCurlOptionOnRequest(Request $request, int $option, $value, $curlHandle = null): void
    {
        switch ($option) {
            case CURLOPT_URL:
                $request->setUrl($value);
                break;
            case CURLOPT_CUSTOMREQUEST:
                $request->setCurlOption(CURLOPT_CUSTOMREQUEST, $value);
                break;
            case CURLOPT_POST:
                if (true == $value) {
                    $request->setMethod('POST');
                }
                break;
            case CURLOPT_POSTFIELDS:
                // todo: check for file @
                if (\is_array($value)) {
                    foreach ($value as $name => $fieldValue) {
                        $request->setPostField($name, $fieldValue);
                    }

                    if (0 == \count($value)) {
                        $request->removeHeader('Content-Type');
                    }
                } elseif (!empty($value)) {
                    // Empty values are ignored to be consistent with how requests are read out of
                    // storage using \VCR\Request::fromArray(array $request).
                    $request->setBody($value);
                }
                $request->setMethod('POST');
                break;
            case CURLOPT_HTTPHEADER:
                foreach ($value as $header) {
                    $headerParts = explode(': ', $header, 2);
                    if (!isset($headerParts[1])) {
                        $headerParts[0] = rtrim($headerParts[0], ':');
                        $headerParts[1] = '';
                    }
                    $request->setHeader($headerParts[0], $headerParts[1]);
                }
                break;
            case CURLOPT_FILE:
            case CURLOPT_HEADER:
            case CURLOPT_WRITEFUNCTION:
            case CURLOPT_HEADERFUNCTION:
            case CURLOPT_UPLOAD:
                // Ignore header, file and writer functions.
                // These options are stored and will be handled later in handleOutput().
                break;
            default:
                $request->setCurlOption($option, $value);
                break;
        }
    }

    /**
     * Makes sure we've properly handled the POST body, such as ensuring that
     * CURLOPT_INFILESIZE is set if CURLOPT_READFUNCTION is set.
     *
     * @param Request  $request    request to set cURL option to
     * @param resource $curlHandle cURL handle associated with the request
     */
    public static function validateCurlPOSTBody(Request $request, $curlHandle = null): void
    {
        $readFunction = $request->getCurlOption(CURLOPT_READFUNCTION);
        if (null === $readFunction) {
            return;
        }

        // Guzzle 4 sometimes sets the post body in CURLOPT_POSTFIELDS even if
        // they have already set CURLOPT_READFUNCTION.
        if ($request->getBody()) {
            return;
        }

        $bodySize = $request->getCurlOption(CURLOPT_INFILESIZE);
        Assertion::notEmpty($bodySize, 'To set a CURLOPT_READFUNCTION, CURLOPT_INFILESIZE must be set.');
        $body = \call_user_func_array($readFunction, [$curlHandle, fopen('php://memory', 'r'), $bodySize]);
        $request->setBody($body);
    }

    /**
     * A wrapper around call_user_func that attempts to properly handle private
     * and protected methods on objects.
     *
     * @param mixed    $callback   The callable to pass to call_user_func
     * @param resource $curlHandle cURL handle associated with the request
     * @param mixed    $argument   The third argument to pass to call_user_func
     *
     * @return mixed value returned by the callback function
     */
    private static function callFunction($callback, $curlHandle, $argument)
    {
        if (!\is_callable($callback) && \is_array($callback) && 2 === \count($callback)) {
            // This is probably a private or protected method on an object. Try and
            // make it accessible.
            $method = new \ReflectionMethod($callback[0], $callback[1]);
            $method->setAccessible(true);

            return $method->invoke($callback[0], $curlHandle, $argument);
        } else {
            return \call_user_func($callback, $curlHandle, $argument);
        }
    }
}
