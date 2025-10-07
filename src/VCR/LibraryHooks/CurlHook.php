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
        private StreamProcessor $processor,
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
        // Clean up handles where callbacks have been set to NULL to prevent stale state.
        // Note: isset() returns false for null values, so we use array_key_exists().
        foreach (self::$curlOptions as $handleId => $options) {
            $writeIsNull = \array_key_exists(\CURLOPT_WRITEFUNCTION, $options) && null === $options[\CURLOPT_WRITEFUNCTION];
            $headerIsNull = \array_key_exists(\CURLOPT_HEADERFUNCTION, $options) && null === $options[\CURLOPT_HEADERFUNCTION];
            $hasNullCallbacks = $writeIsNull || $headerIsNull;

            if ($hasNullCallbacks) {
                unset(self::$requests[$handleId]);
                unset(self::$responses[$handleId]);
                unset(self::$curlOptions[$handleId]);
                unset(self::$lastErrors[$handleId]);
                unset(self::$multiReturnValues[$handleId]);
            }
        }

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
     * @param string           $method cURL method to call, example: curl_info()
     * @param array<int,mixed> $args   cURL arguments for this function
     *
     * @return mixed cURL function return type
     */
    public static function __callStatic(string $method, array $args)
    {
        if (self::DISABLED == static::$status) {
            if ('curl_multi_exec' === $method) {
                return curl_multi_exec($args[0], $args[1]);
            }

            if (!\function_exists($method)) {
                trigger_error("Call to undefined function {$method}()", \E_USER_ERROR);
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
            $handleId = (int) $curlHandle;

            self::$requests[$handleId] = new Request('GET', $url);
            self::$curlOptions[$handleId] = [];
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
        unset(self::$lastErrors[(int) $curlHandle]);
        unset(self::$multiReturnValues[(int) $curlHandle]);
    }

    /**
     * Close a cURL handle.
     *
     * @see http://www.php.net/manual/en/function.curl-close.php
     */
    public static function curlClose(\CurlHandle $curlHandle): void
    {
        $handleId = (int) $curlHandle;

        curl_setopt($curlHandle, \CURLOPT_WRITEFUNCTION, null);
        curl_setopt($curlHandle, \CURLOPT_HEADERFUNCTION, null);

        unset(self::$requests[$handleId]);
        unset(self::$responses[$handleId]);
        unset(self::$curlOptions[$handleId]);
        unset(self::$lastErrors[$handleId]);
        unset(self::$multiReturnValues[$handleId]);

        foreach (self::$multiHandles as $multiId => $handles) {
            unset(self::$multiHandles[$multiId][$handleId]);
        }

        curl_close($curlHandle);
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
            $handleId = (int) $curlHandle;

            if (!isset(self::$requests[$handleId])) {
                self::$requests[$handleId] = new Request('GET', null);
            }

            if (!isset(self::$curlOptions[$handleId])) {
                self::$curlOptions[$handleId] = [];
            }

            // Snapshot curl options before execution. TraceableHttpClient may set callbacks to NULL
            // during handleOutput, causing options to be modified mid-execution.
            $curlOptionsSnapshot = self::$curlOptions[$handleId] ?? [];

            $request = self::$requests[$handleId];
            CurlHelper::validateCurlPOSTBody($request, $curlHandle);

            $requestCallback = self::$requestCallback;
            Assertion::isCallable($requestCallback);
            self::$responses[$handleId] = $requestCallback($request);

            // Use snapshot to prevent reading stale data from the real curl handle.
            // Gzip decompression is handled in CurlHelper::handleOutput().
            return CurlHelper::handleOutput(
                self::$responses[$handleId],
                $curlOptionsSnapshot,
                $curlHandle,
                $curlOptionsSnapshot[\CURLOPT_PRIVATE] ?? null
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

        $stillRunning = 0;

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
            $handle = array_pop(self::$multiExecLastChs);
            $result = \CURLE_OK;

            if (isset(self::$lastErrors[(int) $handle])) {
                $result = self::$lastErrors[(int) $handle]->getCode();
            }

            $info = [
                'msg' => \CURLMSG_DONE,
                'handle' => $handle,
                'result' => $result,
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
        $handleId = (int) $curlHandle;

        // Special handling for CURLINFO_PRIVATE to return the value we stored.
        if (\CURLINFO_PRIVATE === $option
            && isset(self::$curlOptions[$handleId])
            && isset(self::$curlOptions[$handleId][\CURLOPT_PRIVATE])) {
            $value = self::$curlOptions[$handleId][\CURLOPT_PRIVATE];

            return $value;
        }
        if (isset(self::$responses[$handleId])) {
            $result = CurlHelper::getCurlOptionFromResponse(
                self::$responses[$handleId],
                $option
            );

            return $result;
        }

        // Fallback to real curl_getinfo when no VCR response is available yet.
        if (0 === $option) {
            $info = curl_getinfo($curlHandle);

            return $info;
        }
        $result = curl_getinfo($curlHandle, $option);

        return $result;
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
        $handleId = (int) $curlHandle;

        if (!isset(self::$requests[$handleId])) {
            self::$requests[$handleId] = new Request('GET', null);
        }

        if (!isset(self::$curlOptions[$handleId])) {
            self::$curlOptions[$handleId] = [];
        }

        // Note: Symfony HttpClient decompresses gzip responses internally based on the Accept-Encoding
        // header it sends. When VCR replays a gzipped response, Symfony expects it already decompressed
        // but receives it compressed, causing a mismatch. We use VCRHttpClient wrapper for proper handling.
        CurlHelper::setCurlOptionOnRequest(self::$requests[$handleId], $option, $value);

        static::$curlOptions[$handleId][$option] = $value;

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
