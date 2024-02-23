<?php

declare(strict_types=1);

namespace VCR\LibraryHooks;

use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Request;
use VCR\Response;
use VCR\Util\Assertion;
use VCR\Util\CurlException;
use VCR\Util\CurlHelper;
use VCR\Util\StreamProcessor;
use VCR\Util\TextUtil;

class CurlHook implements LibraryHook
{
    protected static ?\Closure $requestCallback;

    protected static string $status = self::DISABLED;

    /**
     * @var Request[] all requests which have been intercepted
     */
    protected static array $requests = [];

    /**
     * @var Response[] all responses which have been intercepted
     */
    protected static array $responses = [];

    /**
     * @var array<int,mixed> additional curl options, which are not stored within a request
     */
    protected static array $curlOptions = [];

    /**
     * @var array<int, array<\CurlHandle>> all curl handles which belong to curl_multi handles
     */
    protected static array $multiHandles = [];

    /**
     * @var array<\CurlHandle> last active curl_multi_exec() handles
     */
    protected static array $multiExecLastChs = [];

    /**
     * @var array<int, string|null> return values of curl_multi responses
     */
    protected static array $multiReturnValues = [];

    /**
     * @var CurlException[] last cURL error, as a CurlException
     */
    protected static array $lastErrors = [];

    public function __construct(
        private AbstractCodeTransform $codeTransformer,
        private StreamProcessor $processor
    ) {
    }

    public function enable(\Closure $requestCallback): void
    {
        if (self::ENABLED == static::$status) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        self::$requestCallback = $requestCallback;

        static::$status = self::ENABLED;
    }

    public function disable(): void
    {
        self::$requestCallback = null;

        static::$status = self::DISABLED;
    }

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
     * @see http://www.php.net/manual/en/function.curl-init.php
     */
    public static function curlInit(?string $url = null): \CurlHandle|false
    {
        $curlHandle = curl_init($url);
        if (false !== $curlHandle) {
            self::$requests[(int) $curlHandle] = new Request('GET', $url);
            self::$curlOptions[(int) $curlHandle] = [];
        }

        return $curlHandle;
    }

    /**
     * @see http://www.php.net/manual/en/function.curl-reset.php
     */
    public static function curlReset(\CurlHandle $curlHandle): void
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
     * @return mixed Returns TRUE on success or FALSE on failure.
     *               However, if the CURLOPT_RETURNTRANSFER option is set, it will return the
     *               result on success, FALSE on failure.
     */
    public static function curlExec(\CurlHandle $curlHandle)
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
     */
    public static function curlMultiAddHandle(\CurlMultiHandle $multiHandle, \CurlHandle $curlHandle): void
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
     */
    public static function curlMultiRemoveHandle(\CurlMultiHandle $multiHandle, \CurlHandle $curlHandle): void
    {
        if (isset(self::$multiHandles[(int) $multiHandle][(int) $curlHandle])) {
            unset(self::$multiHandles[(int) $multiHandle][(int) $curlHandle]);
        }
    }

    /**
     * Run the sub-connections of the current cURL handle.
     *
     * @see http://www.php.net/manual/en/function.curl-multi-exec.php
     */
    public static function curlMultiExec(\CurlMultiHandle $multiHandle, ?int &$stillRunning): int
    {
        if (isset(self::$multiHandles[(int) $multiHandle])) {
            foreach (self::$multiHandles[(int) $multiHandle] as $curlHandle) {
                if (!isset(self::$responses[(int) $curlHandle])) {
                    self::$multiExecLastChs[] = $curlHandle;
                    self::$multiReturnValues[(int) $curlHandle] = self::curlExec($curlHandle);
                }
            }
        }

        return \CURLM_OK;
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
                'msg' => \CURLMSG_DONE,
                'handle' => array_pop(self::$multiExecLastChs),
                'result' => \CURLE_OK,
            ];

            return $info;
        }

        return false;
    }

    /**
     * Return the content of a cURL handle if CURLOPT_RETURNTRANSFER is set.
     *
     * @see https://www.php.net/manual/en/function.curl-multi-getcontent.php
     *
     * @return string|null return the content of a cURL handle if CURLOPT_RETURNTRANSFER is set
     */
    public static function curlMultiGetcontent(\CurlHandle $curlHandle): ?string
    {
        return self::$multiReturnValues[(int) $curlHandle] ?? null;
    }

    /**
     * Get information regarding a specific transfer.
     *
     * @see http://www.php.net/manual/en/function.curl-getinfo.php
     */
    public static function curlGetinfo(\CurlHandle $curlHandle, int $option = 0): mixed
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
     * @param mixed $value the value to be set on option
     */
    public static function curlSetopt(\CurlHandle $curlHandle, int $option, $value): bool
    {
        CurlHelper::setCurlOptionOnRequest(self::$requests[(int) $curlHandle], $option, $value);

        static::$curlOptions[(int) $curlHandle][$option] = $value;

        return curl_setopt($curlHandle, $option, $value);
    }

    /**
     * Set multiple options for a cURL transfer.
     *
     * @see http://www.php.net/manual/en/function.curl-setopt-array.php
     *
     * @param array<int, mixed> $options an array specifying which options to set and their values
     */
    public static function curlSetoptArray(\CurlHandle $curlHandle, array $options): void
    {
        foreach ($options as $option => $value) {
            static::curlSetopt($curlHandle, $option, $value);
        }
    }

    /**
     * Return a string containing the last error for the current session.
     *
     * @see https://php.net/manual/en/function.curl-error.php
     */
    public static function curlError(\CurlHandle $curlHandle): string
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
     */
    public static function curlErrno(\CurlHandle $curlHandle): int
    {
        if (isset(self::$lastErrors[(int) $curlHandle])) {
            return self::$lastErrors[(int) $curlHandle]->getCode();
        }

        return 0;
    }
}
