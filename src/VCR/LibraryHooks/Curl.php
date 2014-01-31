<?php

namespace VCR\LibraryHooks;

use VCR\Util\Assertion;
use VCR\Request;
use VCR\Response;
use VCR\Filter\AbstractFilter;
use VCR\Util\CurlHelper;
use VCR\Util\StreamProcessor;
use VCR\Util\TextUtil;

/**
 * Library hook for curl functions using include-overwrite.
 */
class Curl implements LibraryHook
{

    /**
     * @var \Closure Callback which will be executed when a request is intercepted.
     */
    protected static $requestCallback;

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
     * All curl handles which belong to curl_multi handles.
     */
    protected static $multiHandles = array();

    protected static $multiExecLastCh;

    /**
     * @var AbstractFilter
     */
    private $filter;

    /**
     * @var VCR\Util\StreamProcessor
     */
    private $processor;

    /**
     *
     * @throws \BadMethodCallException in case the Soap extension is not installed.
     */
    public function __construct(AbstractFilter $filter, StreamProcessor $processor)
    {
        $this->processor = $processor;
        $this->filter = $filter;
    }

    /**
     * @inheritDoc
     */
    public function enable(\Closure $requestCallback)
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');

        if (static::$status == self::ENABLED) {
            return;
        }

        $this->filter->register();
        $this->processor->appendFilter($this->filter);
        $this->processor->intercept();

        self::$requestCallback = $requestCallback;

        static::$status = self::ENABLED;
    }

    /**
     * @inheritDoc
     */
    public function disable()
    {
        if (static::$status == self::DISABLED) {
            return;
        }

        self::$requestCallback = null;

        static::$status = self::DISABLED;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled()
    {
        return $this->status == self::ENABLED;
    }

    /**
     * Calls default curl functions if library hook is disabled.
     *
     * @param  string $method [description]
     * @param  array  $args   [description]
     * @return mixed  Curl function return type.
     */
    public static function __callStatic($method, $args)
    {
        // Call original when disabled
        if (static::$status == self::DISABLED) {
            if ($method === 'curl_multi_exec') {
                // curl_multi_exec expects to be called with args by reference
                // which call_user_func_array doesn't do.
                return \curl_multi_exec($args[0], $args[1]);
            }

            return \call_user_func_array($method, $args);
        }

        if ($method === 'curl_multi_exec') {
            // curl_multi_exec expects to be called with args by reference
            // which call_user_func_array doesn't do.
            return self::curlMultiExec($args[0], $args[1]);
        }

        $localMethod = TextUtil::underscoreToLowerCamelcase($method);
        return \call_user_func_array(array(__CLASS__, $localMethod), $args);
    }

    public static function curlInit($url = null)
    {
        $ch = \curl_init($url);
        self::$requests[(int) $ch] = new Request('GET', $url);

        return $ch;
    }

    public static function curlExec($ch)
    {
        $requestCallback = self::$requestCallback;
        self::$responses[(int) $ch] = $requestCallback(self::$requests[(int) $ch]);

        return CurlHelper::handleOutput(
            self::$responses[(int) $ch],
            self::$curlOptions[(int) $ch],
            $ch
        );
    }

    public static function curlMultiAddHandle($mh, $ch)
    {
        if (isset(self::$multiHandles[(int) $mh])) {
            self::$multiHandles[(int) $mh][] = (int) $ch;
        } else {
            self::$multiHandles[(int) $mh] = array((int) $ch);
        }
    }

    public static function curlMultiRemoveHandle($mh, $ch)
    {
        if (isset(self::$multiHandles[(int) $mh][(int) $ch])) {
            unset(self::$multiHandles[(int) $mh][(int) $ch]);
        }
    }

    public static function curlMultiExec($mh, &$still_running)
    {
        if (isset(self::$multiHandles[(int) $mh])) {
            foreach (self::$multiHandles[(int) $mh] as $ch) {
                if (!isset(self::$responses[(int) $ch])) {
                    self::$multiExecLastCh = $ch;
                    self::curlExec($ch);
                }
            }
        }

        return CURLM_OK;
    }

    public static function curlMultiInfoRead($mh)
    {
        if (self::$multiExecLastCh) {
            $info = array(
                'msg' => CURLMSG_DONE,
                'handle' => self::$multiExecLastCh,
                'result' => CURLE_OK
            );
            self::$multiExecLastCh = null;

            return $info;
        }

        return false;
    }

    public static function curlGetinfo($ch, $option = 0)
    {
        return CurlHelper::getCurlOptionFromResponse(
            self::$responses[(int) $ch],
            $option
        );
    }

    public static function curlSetopt($ch, $option, $value)
    {
        CurlHelper::setCurlOptionOnRequest(self::$requests[(int) $ch], $option, $value);

        if (!isset(static::$curlOptions[(int) $ch])) {
            static::$curlOptions[(int) $ch] = array();
        }
        static::$curlOptions[(int) $ch][$option] = $value;

        \curl_setopt($ch, $option, $value);
    }

    public static function curlSetoptArray($ch, $options)
    {
        if (is_array($options)) {
            foreach ($options as $option => $value) {
                static::curlSetopt($ch, $option, $value);
            }
        }
    }
}
