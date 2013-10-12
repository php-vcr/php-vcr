<?php

namespace VCR\Util;

use VCR\Request;
use VCR\Response;

/**
* CUrlhelper
*/
class CurlHelper
{
    private static $curlInfoList = array(
        CURLINFO_HTTP_CODE,
        CURLINFO_EFFECTIVE_URL,
        CURLINFO_FILETIME,
        CURLINFO_TOTAL_TIME,
        CURLINFO_NAMELOOKUP_TIME,
        CURLINFO_CONNECT_TIME,
        CURLINFO_PRETRANSFER_TIME,
        CURLINFO_STARTTRANSFER_TIME,
        CURLINFO_REDIRECT_TIME,
        CURLINFO_SIZE_UPLOAD,
        CURLINFO_SIZE_DOWNLOAD,
        CURLINFO_SPEED_DOWNLOAD,
        CURLINFO_SPEED_UPLOAD,
        CURLINFO_HEADER_SIZE,
        CURLINFO_HEADER_OUT,
        CURLINFO_REQUEST_SIZE,
        CURLINFO_SSL_VERIFYRESULT,
        CURLINFO_CONTENT_LENGTH_DOWNLOAD,
        CURLINFO_CONTENT_LENGTH_UPLOAD
    );

    public static function handleOutput(Response $response, array $curlOptions, $ch)
    {
        if (isset($curlOptions[CURLOPT_HEADERFUNCTION])) {
            $headers = $response->getRawHeaders();
            call_user_func($curlOptions[CURLOPT_HEADERFUNCTION], $ch, $headers);
        }

        $body = (string) $response->getBody(true);

        if (isset($curlOptions[CURLOPT_FILE])) {
            $fp = $curlOptions[CURLOPT_FILE];
            fwrite($fp, $body);
            fflush($fp);
        } elseif (isset($curlOptions[CURLOPT_RETURNTRANSFER]) && $curlOptions[CURLOPT_RETURNTRANSFER] == true) {
            return $body;
        } elseif (isset($curlOptions[CURLOPT_WRITEFUNCTION])) {
            call_user_func($curlOptions[CURLOPT_WRITEFUNCTION], $ch, $body);
        } else {
            echo $body;
        }
    }

    public static function getCurlOptionFromResponse(Response $response, $option = 0)
    {
        switch ($option) {
            case 0: // 0 == array of all curl options
                $info = array();
                array_map(
                    function ($curlInfo) use (&$info, $response) {
                        return $info[$curlInfo] = $response->getInfo($curlInfo);
                    },
                    self::$curlInfoList
                );
                break;
            case CURLINFO_HTTP_CODE:
                $info = $response->getStatusCode();
                break;
            case CURLINFO_SIZE_DOWNLOAD:
                $info = $response->getHeader('Content-Length');
                break;
            default:
                $info = $response->getInfo($option);
                break;
        }

        if (!is_null($info)) {
            return $info;
        }

        $constants = get_defined_constants(true);
        $constantNames = array_flip($constants['curl']);
        die("Todo: {$constantNames[$option]} ({$option}) ");
    }

    public static function setCurlOptionOnRequest(Request $request, $option, $value)
    {
        switch ($option) {
            case CURLOPT_URL:
                $request->setUrl($value);
                break;
            case CURLOPT_FOLLOWLOCATION:
                $request->getParams()->set('redirect.disable', !$value);
                break;
            case CURLOPT_MAXREDIRS:
                $request->getParams()->set('redirect.max', $value);
                break;
            case CURLOPT_POST:
                if ($value == true) {
                    $request->setMethod('POST');
                }
                break;
            case CURLOPT_POSTFIELDS:
                // check for file @
                if (is_string($value)) {
                    parse_str($value, $value);
                }
                foreach ($value as $key => $value) {
                    $request->setPostField($key, $value);
                }
                break;
            case CURLOPT_HTTPHEADER:
                $headers = array();
                foreach ($value as $header) {
                    $headerParts = explode(': ', $header, 2);
                    if (isset($headerParts[1])) {
                        $headers[$headerParts[0]] = $headerParts[1];
                    }
                }
                $request->addHeaders($headers);
                break;
            case CURLOPT_FILE:
                $additionalCurlOpts[CURLOPT_FILE] = $value;
                break;
            case CURLOPT_WRITEFUNCTION:
            case CURLOPT_HEADERFUNCTION:
                // Ignore writer and header functions
                break;
            default:
                $request->getCurlOptions()->set($option, $value);
                break;
        }

    }

}
