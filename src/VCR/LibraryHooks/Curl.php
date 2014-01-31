<?php

namespace VCR\LibraryHooks;

use VCR\Util\Assertion;
use VCR\Request;
use VCR\Response;
use VCR\Filter\AbstractFilter;
use VCR\Util\CurlHelper;
use VCR\Util\StreamProcessor;

/**
 * Library hook for curl functions using include-overwrite.
 */
class Curl implements LibraryHook
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
    public function enable(\Closure $handleRequestCallback)
    {
        Assertion::isCallable($handleRequestCallback, 'No valid callback for handling requests defined.');

        if (static::$status == self::ENABLED) {
            return;
        }

        $this->filter->register();
        $this->processor->appendFilter($this->filter);
        $this->processor->intercept();

        self::$handleRequestCallback = $handleRequestCallback;

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

        self::$handleRequestCallback = null;

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
     * @param  array $args   [description]
     * @return mixed Curl function return type.
     */
    public static function __callStatic($method, $args)
    {
        // Call original when disabled
        if (static::$status == self::DISABLED) {
            if ($method === 'curl_multi_exec') {
                return curl_multi_exec($args[0], $args[1]);
            }
            return call_user_func_array($method, $args);
        }

        if ($method === 'curl_multi_exec') {
            return self::multiExec($args[0], $args[1]);
        }

        $localMethod = static::buildLocalMethodName($method);
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
        $handleRequestCallback = self::$handleRequestCallback;
        self::$responses[(int) $ch] = $handleRequestCallback(self::$requests[(int) $ch]);

        return CurlHelper::handleOutput(
            self::$responses[(int) $ch],
            self::$curlOptions[(int) $ch],
            $ch
        );
    }

    public static function multiAddHandle($mh, $ch)
    {
        if (isset(self::$multiHandles[(int) $mh])) {
            self::$multiHandles[(int) $mh][] = (int) $ch;
        } else {
            self::$multiHandles[(int) $mh] = array((int) $ch);
        }
    }

    public static function multiRemoveHandle($mh, $ch)
    {
        if (isset(self::$multiHandles[(int) $mh][(int) $ch])) {
            unset(self::$multiHandles[(int) $mh][(int) $ch]);
        }
    }

    public static function multiExec($mh, &$still_running)
    {
        if (isset(self::$multiHandles[(int) $mh])) {
            foreach (self::$multiHandles[(int) $mh] as $ch) {
                if (!isset(self::$responses[(int) $ch])) {
                    self::$multiExecLastCh = $ch;
                    self::exec($ch);
                }
            }
        }
        return CURLM_OK;
    }

    public static function multiInfoRead($mh)
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

    public static function setoptArray($ch, $options)
    {
        if (is_array($options)) {
            foreach ($options as $option => $value) {
                static::setopt($ch, $option, $value);
            }
        }
    }

    protected static function buildLocalMethodName($method)
    {
        $localMethod = str_replace('curl_', '', $method);

        // CamalCase. Example: multi_exec -> multiExec
        $localMethod = preg_replace_callback(
            '/_(.?)/',
            function ($matches) {
                return strtoupper($matches[1]);
            },
            $localMethod
        );

        return $localMethod;
    }
}
