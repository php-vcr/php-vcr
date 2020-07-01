<?php

namespace VCR\LibraryHooks;

use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Request;
use VCR\Response;
use VCR\Util\Assertion;
use VCR\Util\CurlException;
use VCR\Util\CurlHelper;
use VCR\Util\StreamProcessor;
use VCR\Util\TextUtil;

/**
 * Library hook for curl functions using include-overwrite.
 */
class CurlHook implements LibraryHook
{
    /**
     * @var \Closure|null callback which will be executed when a request is intercepted
     */
    protected static $requestCallback;

    /**
     * @var string current status of this hook, either enabled or disabled
     */
    protected static $status = self::DISABLED;

    /**
     * @var Request[] all requests which have been intercepted
     */
    protected static $requests = [];

    /**
     * @var Response[] all responses which have been intercepted
     */
    protected static $responses = [];

    /**
     * @var array<int,mixed> additinal curl options, which are not stored within a request
     */
    protected static $curlOptions = [];

    /**
     * @var array<int, array> all curl handles which belong to curl_multi handles
     */
    protected static $multiHandles = [];

    /**
     * @var array<int, array> last active curl_multi_exec() handles
     */
    protected static $multiExecLastChs = [];

    /**
     * @var CurlException[] last cURL error, as a CurlException
     */
    protected static $lastErrors = [];

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
     * @throws \BadMethodCallException in case the cURL extension is not installed
     */
    public function __construct(AbstractCodeTransform $codeTransformer, StreamProcessor $processor)
    {
        if (!\function_exists('curl_version')) {
            // @codeCoverageIgnoreStart
            throw new \BadMethodCallException('cURL extension not installed, please disable the cURL library hook');
            // @codeCoverageIgnoreEnd
        }
        $this->processor = $processor;
        $this->codeTransformer = $codeTransformer;
    }

    /**
     * {@inheritdoc}
     */
    public function enable(\Closure $requestCallback): void
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');

        if (self::ENABLED == static::$status) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        self::$requestCallback = $requestCallback;

        static::$status = self::ENABLED;
    }

    /**
     * {@inheritdoc}
     */
    public function disable(): void
    {
        if (self::DISABLED == static::$status) {
            return;
        }

        self::$requestCallback = null;

        static::$status = self::DISABLED;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return self::ENABLED == self::$status;
    }

    /**
     * Calls the intercepted curl method if library hook is disabled, otherwise the real one.
     *
     * @param callable&string  $method cURL method to call, example: curl_info()
     * @param array<int,mixed> $args   cURL arguments for this function
     *
     * @return mixed cURL function return type
     */
    public static function __callStatic($method, array $args)
    {
        // Call original when disabled
        if (self::DISABLED == static::$status) {
            if ('curl_multi_exec' === $method) {
                // curl_multi_exec expects to be called with args by reference
                // which call_user_func_array doesn't do.
                return curl_multi_exec($args[0], $args[1]);
            }

            return \call_user_func_array($method, $args);
        }

        if ('curl_multi_exec' === $method) {
            // curl_multi_exec expects to be called with args by reference
            // which call_user_func_array doesn't do.
            return self::curlMultiExec($args[0], $args[1]);
        }

        $localMethod = TextUtil::underscoreToLowerCamelcase($method);

        $callable = [__CLASS__, $localMethod];

        Assertion::isCallable($callable);

        return \call_user_func_array($callable, $args);
    }

    /**
     * Initialize a cURL session.
     *
     * @see http://www.php.net/manual/en/function.curl-init.php
     *
     * @param string|null $url (Optional) url
     *
     * @return resource|false cURL handle
     */
    public static function curlInit(?string $url = null)
    {
        $curlHandle = curl_init($url);
        if (false !== $curlHandle) {
            self::$requests[(int) $curlHandle] = new Request('GET', $url);
            self::$curlOptions[(int) $curlHandle] = [];
        }

        return $curlHandle;
    }

    /**
     * Reset a cURL session.
     *
     * @see http://www.php.net/manual/en/function.curl-reset.php
     *
     * @param resource $curlHandle a cURL handle returned by curl_init()
     */
    public static function curlReset($curlHandle): void
    {
        curl_reset($curlHandle);
        self::$requests[(int) $curlHandle] = new Request('GET', null);
        self::$curlOptions[(int) $curlHandle] = [];
        unset(self::$responses[(int) $curlHandle]);
    }

    /**
     * Perform a cURL session.
     *
     * @see http://www.php.net/manual/en/function.curl-exec.php
     *
     * @param resource $curlHandle a cURL handle returned by curl_init()
     *
     * @return mixed Returns TRUE on success or FALSE on failure.
     *               However, if the CURLOPT_RETURNTRANSFER option is set, it will return the
     *               result on success, FALSE on failure.
     */
    public static function curlExec($curlHandle)
    {
        try {
            $request = self::$requests[(int) $curlHandle];
            CurlHelper::validateCurlPOSTBody($request, $curlHandle);

            $requestCallback = self::$requestCallback;
            Assertion::isCallable($requestCallback);
            self::$responses[(int) $curlHandle] = $requestCallback($request);

            return CurlHelper::handleOutput(
                self::$responses[(int) $curlHandle],
                self::$curlOptions[(int) $curlHandle],
                $curlHandle
            );
        } catch (CurlException $e) {
            self::$lastErrors[(int) $curlHandle] = $e;

            return false;
        }
    }

    /**
     * Add a normal cURL handle to a cURL multi handle.
     *
     * @see http://www.php.net/manual/en/function.curl-multi-add-handle.php
     *
     * @param resource $multiHandle a cURL multi handle returned by curl_multi_init()
     * @param resource $curlHandle  a cURL handle returned by curl_init()
     */
    public static function curlMultiAddHandle($multiHandle, $curlHandle): void
    {
        if (!isset(self::$multiHandles[(int) $multiHandle])) {
            self::$multiHandles[(int) $multiHandle] = [];
        }

        self::$multiHandles[(int) $multiHandle][(int) $curlHandle] = $curlHandle;
    }

    /**
     * Remove a multi handle from a set of cURL handles.
     *
     * @see http://www.php.net/manual/en/function.curl-multi-remove-handle.php
     *
     * @param resource $multiHandle a cURL multi handle returned by curl_multi_init()
     * @param resource $curlHandle  a cURL handle returned by curl_init()
     */
    public static function curlMultiRemoveHandle($multiHandle, $curlHandle): void
    {
        if (isset(self::$multiHandles[(int) $multiHandle][(int) $curlHandle])) {
            unset(self::$multiHandles[(int) $multiHandle][(int) $curlHandle]);
        }
    }

    /**
     * Run the sub-connections of the current cURL handle.
     *
     * @see http://www.php.net/manual/en/function.curl-multi-exec.php
     *
     * @param resource $multiHandle  a cURL multi handle returned by curl_multi_init()
     * @param int      $stillRunning a reference to a flag to tell whether the operations are still running
     *
     * @return int a cURL code defined in the cURL Predefined Constants
     */
    public static function curlMultiExec($multiHandle, ?int &$stillRunning): int
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
     * @see http://www.php.net/manual/en/function.curl-multi-info-read.php
     *
     * @return array<string,mixed>|bool on success, returns an associative array for the message, FALSE on failure
     */
    public static function curlMultiInfoRead()
    {
        if (!empty(self::$multiExecLastChs)) {
            $info = [
                'msg' => CURLMSG_DONE,
                'handle' => array_pop(self::$multiExecLastChs),
                'result' => CURLE_OK,
            ];

            return $info;
        }

        return false;
    }

    /**
     * Get information regarding a specific transfer.
     *
     * @see http://www.php.net/manual/en/function.curl-getinfo.php
     *
     * @param resource $curlHandle a cURL handle returned by curl_init()
     * @param int      $option     a cURL option defined in the cURL Predefined Constants
     *
     * @return mixed
     */
    public static function curlGetinfo($curlHandle, int $option = 0)
    {
        if (isset(self::$responses[(int) $curlHandle])) {
            return CurlHelper::getCurlOptionFromResponse(
                self::$responses[(int) $curlHandle],
                $option
            );
        } elseif (isset(self::$lastErrors[(int) $curlHandle])) {
            return self::$lastErrors[(int) $curlHandle]->getInfo();
        } else {
            throw new \RuntimeException('Unexpected error, could not find curl_getinfo in response or errors');
        }
    }

    /**
     * Set an option for a cURL transfer.
     *
     * @see http://www.php.net/manual/en/function.curl-setopt.php
     *
     * @param resource $curlHandle a cURL handle returned by curl_init()
     * @param int      $option     the CURLOPT_XXX option to set
     * @param mixed    $value      the value to be set on option
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public static function curlSetopt($curlHandle, int $option, $value): bool
    {
        CurlHelper::setCurlOptionOnRequest(self::$requests[(int) $curlHandle], $option, $value, $curlHandle);

        static::$curlOptions[(int) $curlHandle][$option] = $value;

        return curl_setopt($curlHandle, $option, $value);
    }

    /**
     * Set multiple options for a cURL transfer.
     *
     * @see http://www.php.net/manual/en/function.curl-setopt-array.php
     *
     * @param resource          $curlHandle a cURL handle returned by curl_init()
     * @param array<int, mixed> $options    an array specifying which options to set and their values
     */
    public static function curlSetoptArray($curlHandle, array $options): void
    {
        if (\is_array($options)) {
            foreach ($options as $option => $value) {
                static::curlSetopt($curlHandle, $option, $value);
            }
        }
    }

    /**
     * Return a string containing the last error for the current session.
     *
     * @see https://php.net/manual/en/function.curl-error.php
     *
     * @param resource $curlHandle
     *
     * @return string the error message or '' (the empty string) if no
     *                error occurred
     */
    public static function curlError($curlHandle): string
    {
        if (isset(self::$lastErrors[(int) $curlHandle])) {
            return self::$lastErrors[(int) $curlHandle]->getMessage();
        }

        return '';
    }

    /**
     * Return the last error number.
     *
     * @see https://php.net/manual/en/function.curl-errno.php
     *
     * @param resource $curlHandle
     *
     * @return int the error number or 0 (zero) if no error
     *             occurred
     */
    public static function curlErrno($curlHandle): int
    {
        if (isset(self::$lastErrors[(int) $curlHandle])) {
            return self::$lastErrors[(int) $curlHandle]->getCode();
        }

        return 0;
    }
}
