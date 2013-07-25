<?php

namespace VCR\LibraryHooks;

use \VCR\Request;
use \VCR\Response;
use \VCR\Assertion;
use \VCR\Util\CurlHelper;

/**
 * Library hook for curl functions using include-overwrite.
 */
class CurlRewrite implements LibraryHookInterface
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
     * @inherit
     */
    public function enable(\Closure $handleRequestCallback)
    {
        Assertion::isCallable($handleRequestCallback, 'No valid callback for handling requests defined.');
        self::$handleRequestCallback = $handleRequestCallback;
        self::$status = self::ENABLED;
    }

    /**
     * @inherit
     */
    public function disable()
    {
        self::$status = self::DISABLED;
        self::$handleRequestCallback = null;
    }

    public static function __callStatic($method, $args)
    {
        // Call original when disabled
        if (self::$status == self::DISABLED) {
            return call_user_func_array($method, $args);
        }

        $localMethod = str_replace('curl_', '', $method);
        return call_user_func_array(array(__CLASS__, $localMethod), $args);
    }

    public static function init($url = null)
    {
        $ch = \curl_init($url);
        self::$requests[(int) $ch] = new Request('GET', $url);
        return $ch;
    }

    public static function exec($ch)
    {
        //  workaround for this?
        $handleRequestCallback = self::$handleRequestCallback;
        self::$responses[(int) $ch] = $handleRequestCallback(self::$requests[(int) $ch]);

        return CurlHelper::handleOutput(
            self::$responses[(int) $ch],
            self::$curlOptions[(int) $ch],
            $ch
        );
    }

    public static function getinfo($ch, $option = 0)
    {
        return CurlHelper::getCurlOptionFromResponse(
            self::$responses[(int) $ch],
            $option
        );
    }

    public static function setopt($ch, $option, $value)
    {
        CurlHelper::setCurlOptionOnRequest(self::$requests[(int) $ch], $option, $value);

        if (!isset(static::$curlOptions[(int) $ch])) {
            static::$curlOptions[(int) $ch] = array();
        }
        static::$curlOptions[(int) $ch][$option] = $value;

        \curl_setopt($ch, $option, $value);
    }
}