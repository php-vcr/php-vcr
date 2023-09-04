<?php

declare(strict_types=1);

namespace VCR\LibraryHooks;

use VCR\Response;
use VCR\Util\Assertion;
use VCR\Util\CurlException;
use VCR\Util\StreamHelper;

class StreamWrapperHook implements LibraryHook
{
    protected static ?\Closure $requestCallback;

    protected int $position = 0;

    protected string $status = self::DISABLED;

    protected Response $response;

    /**
     * @var resource current stream context
     */
    public $context;

    public function enable(\Closure $requestCallback): void
    {
        self::$requestCallback = $requestCallback;
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', __CLASS__, \STREAM_IS_URL);

        stream_wrapper_unregister('https');
        stream_wrapper_register('https', __CLASS__, \STREAM_IS_URL);

        $this->status = self::ENABLED;
    }

    public function disable(): void
    {
        self::$requestCallback = null;
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');

        $this->status = self::DISABLED;
    }

    public function isEnabled(): bool
    {
        return self::ENABLED == $this->status;
    }

    /**
     * This method is called immediately after the wrapper is initialized (f.e. by fopen() and file_get_contents()).
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-open.php
     *
     * @param string $path        specifies the URL that was passed to the original function
     * @param string $mode        the mode used to open the file, as detailed for fopen()
     * @param int    $options     holds additional flags set by the streams API
     * @param string $opened_path if the path is opened successfully, and STREAM_USE_PATH is set
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $request = StreamHelper::createRequestFromStreamContext($this->context, $path);

        $requestCallback = self::$requestCallback;
        Assertion::isCallable($requestCallback);
        try {
            $this->response = $requestCallback($request);

            return true;
        } catch (CurlException $e) {
            return false;
        }
    }

    /**
     * Read from stream.
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-read.php
     *
     * @param int $count how many bytes of data from the current position should be returned
     *
     * @return string If there are less than count bytes available, return as many as are available.
     *                If no more data is available, return either FALSE or an empty string.
     */
    public function stream_read(int $count): string
    {
        $ret = substr($this->response->getBody(), $this->position ?? 0, $count);
        $this->position += \strlen($ret);

        return $ret;
    }

    /**
     * @throws \BadMethodCallException if called, because this method is not applicable for this stream
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-write.php
     */
    public function stream_write(string $data): int
    {
        throw new \BadMethodCallException('No writing possible');
    }

    /**
     * Retrieve the current position of a stream.
     *
     * This method is called in response to fseek() to determine the current position.
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-tell.php
     */
    public function stream_tell(): int
    {
        return $this->position;
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-eof.php
     */
    public function stream_eof(): bool
    {
        return $this->position >= \strlen($this->response->getBody());
    }

    /**
     * Retrieve information about a file resource.
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-stat.php
     *
     * @return array<int|string,int> see stat()
     */
    public function stream_stat(): array
    {
        return [];
    }

    /**
     * Retrieve information about a file resource.
     *
     * @see http://www.php.net/manual/en/streamwrapper.url-stat.php
     *
     * @return array<int|string,int> see stat()
     */
    public function url_stat(string $path, int $flags): array
    {
        return [];
    }

    /**
     * Seeks to specific location in a stream.
     *
     * @param int $offset the stream offset to seek to
     * @param int $whence Possible values:
     *                    SEEK_SET - Set position equal to offset bytes.
     *                    SEEK_CUR - Set position to current location plus offset.
     *                    SEEK_END - Set position to end-of-file plus offset.
     *
     * @return bool return TRUE if the position was updated, FALSE otherwise
     */
    public function stream_seek(int $offset, int $whence): bool
    {
        switch ($whence) {
            case \SEEK_SET:
                if ($offset < \strlen($this->response->getBody()) && $offset >= 0) {
                    $this->position = $offset;

                    return true;
                }
                break;
            case \SEEK_CUR:
                if ($offset >= 0) {
                    $this->position += $offset;

                    return true;
                }
                break;
            case \SEEK_END:
                if (\strlen($this->response->getBody()) + $offset >= 0) {
                    $this->position = \strlen($this->response->getBody()) + $offset;

                    return true;
                }
        }

        return false;
    }

    /**
     * @see http://www.php.net/manual/en/streamwrapper.stream-metadata.php
     *
     * @param mixed $var value depending on the option
     */
    public function stream_metadata(string $path, int $option, $var): bool
    {
        return false;
    }
}
