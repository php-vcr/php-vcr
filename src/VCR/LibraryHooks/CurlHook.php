<?php

namespace VCR\LibraryHooks;

use VCR\Util\Assertion;
use VCR\Request;
use VCR\Response;
use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Util\CurlHelper;
use VCR\Util\StreamProcessor;
use VCR\Util\TextUtil;

/**
 * Library hook for curl functions using include-overwrite.
 */
class CurlHook implements LibraryHook
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
     * @var array Additinal curl options, which are not stored within a request.
     */
    protected static $curlOptions = array();

    /**
     * @var array All curl handles which belong to curl_multi handles.
     */
    protected static $multiHandles = array();

    /**
     * @var array Last active curl_multi_exec() handles.
     */
    protected static $multiExecLastChs = array();

    /**
     * @var AbstractCodeTransform
     */
    private $codeTransformer;

    /**
     * @var StreamProcessor
     */
    private $processor;

    /**
     * Creates a new cURL hook instance.
     *
     * @param AbstractCodeTransform  $codeTransformer
     * @param StreamProcessor $processor
     *
     * @throws \BadMethodCallException in case the cURL extension is not installed.
     */
    public function __construct(AbstractCodeTransform $codeTransformer, StreamProcessor $processor)
    {
        if (!function_exists('curl_version')) {
            // @codeCoverageIgnoreStart
            throw new \BadMethodCallException(
                'cURL extension not installed, please disable the cURL library hook'
            );
            // @codeCoverageIgnoreEnd
        }
        $this->processor = $processor;
        $this->codeTransformer = $codeTransformer;
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

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
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
        return self::$status == self::ENABLED;
    }

    /**
     * Calls the intercepted curl method if library hook is disabled, otherwise the real one.
     *
     * @param string $method cURL method to call, example: curl_info()
     * @param array  $args   cURL arguments for this function.
     *
     * @return mixed  cURL function return type.
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

    /**
     * Initialize a cURL session.
     *
     * @link http://www.php.net/manual/en/function.curl-init.php
     * @param string $url (Optional) url.
     *
     * @return resource cURL handle.
     */
    public static function curlInit($url = null)
    {
        $curlHandle = \curl_init($url);
        self::$requests[(int) $curlHandle] = new Request('GET', $url);
        self::$curlOptions[(int) $curlHandle] = array();

        return $curlHandle;
    }

    /**
     * Reset a cURL session.
     *
     * @link http://www.php.net/manual/en/function.curl-reset.php
     * @param resource $curlHandle A cURL handle returned by curl_init().
     */
    public static function curlReset($curlHandle)
    {
        \curl_reset($curlHandle);
        self::$requests[(int) $curlHandle] = new Request('GET', null);
        self::$curlOptions[(int) $curlHandle] = array();
        unset(self::$responses[(int) $curlHandle]);
    }

    /**
     * Perform a cURL session.
     *
     * @link http://www.php.net/manual/en/function.curl-exec.php
     * @param resource $curlHandle A cURL handle returned by curl_init().
     *
     * @return mixed Returns TRUE on success or FALSE on failure.
     * However, if the CURLOPT_RETURNTRANSFER option is set, it will return the
     * result on success, FALSE on failure.
     */
    public static function curlExec($curlHandle)
    {
        $request = self::$requests[(int) $curlHandle];
        CurlHelper::validateCurlPOSTBody($request, $curlHandle);

        $requestCallback = self::$requestCallback;
        self::$responses[(int) $curlHandle] = $requestCallback($request);

        return CurlHelper::handleOutput(
            self::$responses[(int) $curlHandle],
            self::$curlOptions[(int) $curlHandle],
            $curlHandle
        );
    }

    /**
     * Add a normal cURL handle to a cURL multi handle.
     *
     * @link http://www.php.net/manual/en/function.curl-multi-add-handle.php
     * @param resource $multiHandle A cURL multi handle returned by curl_multi_init().
     * @param resource $curlHandle  A cURL handle returned by curl_init().
     */
    public static function curlMultiAddHandle($multiHandle, $curlHandle)
    {
        if (!isset(self::$multiHandles[(int) $multiHandle])) {
            self::$multiHandles[(int) $multiHandle] = array();
        }

        self::$multiHandles[(int) $multiHandle][(int) $curlHandle] = $curlHandle;
    }

    /**
     * Remove a multi handle from a set of cURL handles.
     *
     * @link http://www.php.net/manual/en/function.curl-multi-remove-handle.php
     * @param resource $multiHandle A cURL multi handle returned by curl_multi_init().
     * @param resource $curlHandle A cURL handle returned by curl_init().
     */
    public static function curlMultiRemoveHandle($multiHandle, $curlHandle)
    {
        if (isset(self::$multiHandles[(int) $multiHandle][(int) $curlHandle])) {
            unset(self::$multiHandles[(int) $multiHandle][(int) $curlHandle]);
        }
    }

    /**
     * Run the sub-connections of the current cURL handle.
     *
     * @link http://www.php.net/manual/en/function.curl-multi-exec.php
     * @param resource $multiHandle A cURL multi handle returned by curl_multi_init().
     * @param integer $stillRunning A reference to a flag to tell whether the operations are still running.
     *
     * @return integer  A cURL code defined in the cURL Predefined Constants.
     */
    public static function curlMultiExec($multiHandle, &$stillRunning)
    {
        if (isset(self::$multiHandles[(int) $multiHandle])) {
            foreach (self::$multiHandles[(int) $multiHandle] as $curlHandle) {
                if (!isset(self::$responses[(int) $curlHandle])) {
                    self::$multiExecLastChs[] = $curlHandle;
                    self::curlExec($curlHandle);
                }
            }
        }

        return CURLM_OK;
    }

    /**
     * Get information about the current transfers.
     *
     * @link http://www.php.net/manual/en/function.curl-multi-info-read.php
     *
     * @return array|bool On success, returns an associative array for the message, FALSE on failure.
     */
    public static function curlMultiInfoRead()
    {
        if (!empty(self::$multiExecLastChs)) {
            $info = array(
                'msg' => CURLMSG_DONE,
                'handle' => array_pop(self::$multiExecLastChs),
                'result' => CURLE_OK
            );

            return $info;
        }

        return false;
    }

    /**
     * Get information regarding a specific transfer.
     *
     * @link http://www.php.net/manual/en/function.curl-getinfo.php
     * @param resource $curlHandle A cURL handle returned by curl_init().
     * @param integer  $option     A cURL option defined in the cURL Predefined Constants.
     *
     * @return mixed
     */
    public static function curlGetinfo($curlHandle, $option = 0)
    {
        // Workaround for CURLINFO_PRIVATE.
        // It can be set AND read before the response is available, e.g by symfony/http-client.
        //   - If the response is available, we read from it.
        //   - If not, we return what was first set.
        if ($option === CURLINFO_PRIVATE && !in_array((int) $curlHandle, self::$responses, true)) {
            return static::$curlOptions[(int) $curlHandle][CURLOPT_PRIVATE];
        }

        return CurlHelper::getCurlOptionFromResponse(
            self::$responses[(int) $curlHandle],
            $option
        );
    }

    /**
     * Set an option for a cURL transfer.
     *
     * @link http://www.php.net/manual/en/function.curl-setopt.php
     * @param resource $curlHandle A cURL handle returned by curl_init().
     * @param integer  $option     The CURLOPT_XXX option to set.
     * @param mixed    $value      The value to be set on option.
     *
     * @return boolean  Returns TRUE on success or FALSE on failure.
     */
    public static function curlSetopt($curlHandle, $option, $value)
    {
        CurlHelper::setCurlOptionOnRequest(self::$requests[(int) $curlHandle], $option, $value, $curlHandle);

        static::$curlOptions[(int) $curlHandle][$option] = $value;

        return \curl_setopt($curlHandle, $option, $value);
    }

    /**
     * Set multiple options for a cURL transfer.
     *
     * @link http://www.php.net/manual/en/function.curl-setopt-array.php
     * @param resource $curlHandle A cURL handle returned by curl_init().
     * @param array    $options    An array specifying which options to set and their values.
     */
    public static function curlSetoptArray($curlHandle, $options)
    {
        if (is_array($options)) {
            foreach ($options as $option => $value) {
                static::curlSetopt($curlHandle, $option, $value);
            }
        }
    }
}
