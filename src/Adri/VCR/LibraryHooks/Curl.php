<?php

namespace Adri\VCR\LibraryHooks;

use Adri\VCR\Configuration;
use Adri\VCR\Request;
use Adri\VCR\Response;

/**
 * Library hook for curl functions.
 */
class Curl implements LibraryHookInterface
{
    private static $status = self::DISABLED;

    /**
     * @var Request
     */
    private static $request;

    /**
     * @var Response
     */
    private static $response;

    private static $handleRequestCallable;

    private static $additionalCurlOpts = array();

    private static $overwriteFunctions = array(
        'curl_init'       => array('$url = null', 'init($url)'),
        'curl_exec'       => array('$resource', 'exec($resource)'),
        'curl_getinfo'    => array('$resource, $option = 0', 'getinfo($resource, $option)'),
        'curl_setopt'     => array('$ch, $option, $value', 'setOpt($ch, $option, $value)'),
    );

    public function __construct(\Closure $handleRequestCallable = null)
    {
        if (!function_exists('runkit_function_redefine')) {
            throw new \BadMethodCallException('For curl support you need to install runkit extension.');
        }

        if (!is_null($handleRequestCallable)) {
            if (!is_callable($handleRequestCallable)) {
                throw new \InvalidArgumentException('No valid callback for handling requests defined.');
            }
            self::$handleRequestCallable = $handleRequestCallable;
        }
    }

    public function enable()
    {
        if (self::$status == self::ENABLED) {
            return;
        }

        foreach (self::$overwriteFunctions as $functionName => $mapping) {
            runkit_function_rename($functionName, $functionName . '_original');

            if (function_exists($functionName . '_temp')) {
                runkit_function_rename($functionName . '_temp', $functionName);
            } else {
                runkit_function_add($functionName, $mapping[0], 'return ' . __CLASS__ . '::' . $mapping[1] . ';');
            }
        }

        self::$status = self::ENABLED;
    }

    public function disable()
    {
        if (self::$status == self::DISABLED) {
            return;
        }

        foreach (self::$overwriteFunctions as $functionName => $mapping) {
            runkit_function_rename($functionName, $functionName . '_temp');
            runkit_function_rename($functionName . '_original', $functionName);
        }

        self::$status = self::DISABLED;
    }

    public static function init($url = null)
    {
        self::$request = new Request('GET', $url);
        return \curl_init_original($url);
    }

    public static function exec($ch)
    {
        $handleRequestCallable = self::$handleRequestCallable;
        self::$response = $handleRequestCallable(self::$request);

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

    public static function getinfo($ch, $option)
    {
        switch ($option) {
            case CURLINFO_HTTP_CODE:
                return self::$response->getStatusCode();
                break;
            case CURLINFO_SIZE_DOWNLOAD:
                return self::$response->getHeader('Content-Length');
                break;
            case CURLFTPMETHOD_NOCWD:
                break;
            default:
                $info = self::$response->getInfo($option);
                if (!is_null($info)) {
                    return $info;
                }
                $constants = get_defined_constants(true);
                $info = array_flip($constants['curl']);
                die("Todo: {$info[$option]} ({$option}) ");
                break;
        }
    }

    protected static function getCurlOption($option)
    {
        return self::$request->getCurlOptions()->get($option);
    }

    public static function setOpt($ch, $option, $value)
    {
        // die( "{$option} = {$value}\n" );
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

        \curl_setopt_original($ch, $option, $value);
    }

}