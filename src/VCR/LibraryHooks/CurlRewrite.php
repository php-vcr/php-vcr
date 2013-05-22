<?php

namespace VCR\LibraryHooks;

use \VCR\Request;
use \VCR\Response;
use \VCR\Assertion;

/**
 * Library hook for curl functions.
 */
class CurlRewrite implements LibraryHookInterface
{

    protected static $handleRequestCallback;
    protected static $status;
    protected static $request;
    protected static $response;
    protected static $additionalCurlOpts = array();


    public function enable(\Closure $handleRequestCallback)
    {
        Assertion::isCallable($handleRequestCallback, 'No valid callback for handling requests defined.');
        self::$handleRequestCallback = $handleRequestCallback;

        if (self::$status == self::ENABLED) {
            return;
        }

        self::$status = self::ENABLED;
    }

    public function disable()
    {
        if (self::$status == self::DISABLED) {
            return;
        }

        self::$status = self::DISABLED;
        self::$handleRequestCallback = null;
    }


    public static function curl_init($url = null)
    {
        self::$request = new Request('GET', $url);
        return \curl_init($url);
    }

    public static function curl_exec($resource)
    {
        if (self::$status == self::DISABLED) {
            return curl_exec($resource);
        }

        $handleRequestCallback = self::$handleRequestCallback;
        self::$response = $handleRequestCallback(self::$request);

        $responseBody = (string) self::$response->getBody(true);

        if (isset(static::$additionalCurlOpts[CURLOPT_FILE])) {
            $fp = static::$additionalCurlOpts[CURLOPT_FILE];
            fwrite($fp, $responseBody);
            fflush($fp);
            static::$additionalCurlOpts[CURLOPT_FILE] = null;
        } else if (static::getCurlOption(CURLOPT_RETURNTRANSFER) == true) {
            return $responseBody;
        } else {
            echo $responseBody;
        }
    }

    public static function curl_getinfo($resource, $option = 0)
    {
        $info = array(
            // CURLFTPMETHOD_NOCWD              => null,
            CURLINFO_HTTP_CODE               => self::$response->getStatusCode(),
            CURLINFO_EFFECTIVE_URL           => self::$response->getInfo($option),
            CURLINFO_FILETIME                => self::$response->getInfo($option),
            CURLINFO_TOTAL_TIME              => self::$response->getInfo($option),
            CURLINFO_NAMELOOKUP_TIME         => self::$response->getInfo($option),
            CURLINFO_CONNECT_TIME            => self::$response->getInfo($option),
            CURLINFO_PRETRANSFER_TIME        => self::$response->getInfo($option),
            CURLINFO_STARTTRANSFER_TIME      => self::$response->getInfo($option),
            CURLINFO_REDIRECT_TIME           => self::$response->getInfo($option),
            CURLINFO_SIZE_UPLOAD             => self::$response->getInfo($option),
            CURLINFO_SIZE_DOWNLOAD           => self::$response->getHeader('Content-Length'),
            CURLINFO_SPEED_DOWNLOAD          => self::$response->getInfo($option),
            CURLINFO_SPEED_UPLOAD            => self::$response->getInfo($option),
            CURLINFO_HEADER_SIZE             => self::$response->getInfo($option),
            CURLINFO_HEADER_OUT              => self::$response->getInfo($option),
            CURLINFO_REQUEST_SIZE            => self::$response->getInfo($option),
            CURLINFO_SSL_VERIFYRESULT        => self::$response->getInfo($option),
            CURLINFO_CONTENT_LENGTH_DOWNLOAD => self::$response->getInfo($option),
            CURLINFO_CONTENT_LENGTH_UPLOAD   => self::$response->getInfo($option),
        );

        if ($option === 0)  {
            return $info;
        }

        if (isset($info[$option])) {
            return $info[$option];
        }

        if (!is_null(self::$response->getInfo($option))) {
            return self::$response->getInfo($option);
        }

        $constants = get_defined_constants(true);
        $constantNames = array_flip($constants['curl']);
        die("Todo: {$constantNames[$option]} ({$option}) ");
    }

    public static function curl_setopt($ch, $option, $value)
    {
        switch ($option) {
            case CURLOPT_URL:
                self::$request->setUrl($value);
                break;
            case CURLOPT_FOLLOWLOCATION:
                self::$request->getParams()->set('redirect.disable', !$value);
                break;
            case CURLOPT_MAXREDIRS:
                self::$request->getParams()->set('redirect.max', $value);
                break;
            case CURLOPT_POST:
                if ($value == true) {
                    self::$request->setMethod('POST');
                }
                break;
            case CURLOPT_POSTFIELDS:
                // check for file @
                if (is_string($value)) {
                    parse_str($value, $value);
                }
                foreach ($value as $key => $value) {
                    self::$request->setPostField($key, $value);
                }
                break;
            case CURLOPT_HTTPHEADER:
                $headers = array();
                foreach ($value as $header) {
                    list($key, $val) = explode(': ', $header, 2);
                    $headers[$key] = $val;
                }
                self::$request->addHeaders($headers);
                break;
            case CURLOPT_FILE:
                self::$additionalCurlOpts[CURLOPT_FILE] = $value;
                break;
            default:
                self::$request->getCurlOptions()->set($option, $value);
                break;
        }

        \curl_setopt($ch, $option, $value);
    }


    protected static function getCurlOption($option)
    {
        return self::$request->getCurlOptions()->get($option);
    }

}