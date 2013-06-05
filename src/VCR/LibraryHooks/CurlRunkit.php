<?php

namespace VCR\LibraryHooks;

use \VCR\Configuration;
use \VCR\Request;
use \VCR\Response;
use \VCR\Assertion;
use \VCR\Util\CurlHelper;

/**
 * Library hook for curl functions.
 */
class CurlRunkit implements LibraryHookInterface
{
    /**
     * @var \Closure Callback which will be executed when a request is intercepted.
     */
    protected static $handleRequestCallback;

    /**
     * @var string Current status of this hook, either enabled or disabled.
     */
    protected static $status = self::DISABLED;

    /**
     * @var Request[] All requests which have been intercepted.
     */
    protected static $requests = array();

    /**
     * @var Response[] All responses which have been intercepted.
     */
    protected static $responses = array();

    /**
     * @var array[] Additinal curl options, which are not stored within a request.
     */
    protected static $curlOptions = array();

    /**
     * @var array Defines curl functions to overwrite with method calls to this class.
     */
    protected static $overwriteFunctions = array(
        'curl_init'       => array('$url = null', 'init($url)'),
        'curl_exec'       => array('$resource', 'exec($resource)'),
        'curl_getinfo'    => array('$resource, $option = 0', 'getInfo($resource, $option)'),
        'curl_setopt'     => array('$ch, $option, $value', 'setOpt($ch, $option, $value)'),
    );

    /**
     * Initializes a new curl libraryhook using ext-runkit.
     *
     * @throws \BadMethodCallException When runkit is not available.
     */
    public function __construct()
    {
        if (!function_exists('runkit_function_redefine')) {
            throw new \BadMethodCallException('For curl support you need to install runkit extension.');
        }

        self::$handleRequestCallback = null;
    }

    /**
     * @inherit
     */
    public function enable(\Closure $handleRequestCallback)
    {
        Assertion::isCallable($handleRequestCallback, 'No valid callback for handling requests defined.');
        self::$handleRequestCallback = $handleRequestCallback;

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

    /**
     * @inherit
     */
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
        self::$handleRequestCallback = null;
    }

    public static function init($url = null)
    {
        $ch = \curl_init_original($url);
        self::$requests[(int) $ch] = new Request('GET', $url);
        return $ch;
    }

    public static function exec($ch)
    {
        $handleRequestCallback = self::$handleRequestCallback;
        self::$responses[(int) $ch] = $handleRequestCallback(self::$requests[(int) $ch]);

        return CurlHelper::handleOutput(
            self::$responses[(int) $ch],
            self::$curlOptions[(int) $ch]
        );
    }

    public static function getInfo($ch, $option = 0)
    {
        return CurlHelper::getCurlOptionFromResponse(
            self::$responses[(int) $ch],
            $option
        );
    }

    public static function setOpt($ch, $option, $value)
    {
        CurlHelper::setCurlOptionOnRequest(self::$requests[(int) $ch], $option, $value);

        if (!isset(static::$curlOptions[(int) $ch])) {
            static::$curlOptions[(int) $ch] = array();
        }
        static::$curlOptions[(int) $ch][$option] = $value;

        \curl_setopt_original($ch, $option, $value);
    }

    public function __destruct()
    {
        self::$handleRequestCallback = null;
    }
}
