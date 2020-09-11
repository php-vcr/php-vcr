<?php

namespace VCR\LibraryHooks;

use VCR\Response;
use VCR\Util\Assertion;
use VCR\Util\CurlException;
use VCR\Util\StreamHelper;

/**
 * Library hook for streamWrapper functions using stream_wrapper_register().
 */
class StreamWrapperHook implements LibraryHook
{
    /**
     * @var \Closure|null callback which will be executed when a request is intercepted
     */
    protected static $requestCallback;

    /**
     * @var int position in the current response body
     */
    protected $position;

    /**
     * @var string current status of this hook, either enabled or disabled
     */
    protected $status = self::DISABLED;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var resource current stream context
     */
    public $context;

    /**
     * {@inheritdoc}
     */
    public function enable(\Closure $requestCallback): void
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');
        self::$requestCallback = $requestCallback;
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', __CLASS__, STREAM_IS_URL);

        stream_wrapper_unregister('https');
        stream_wrapper_register('https', __CLASS__, STREAM_IS_URL);

        $this->status = self::ENABLED;
    }

    /**
     * {@inheritdoc}
     */
    public function disable(): void
    {
        self::$requestCallback = null;
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');

        $this->status = self::DISABLED;
    }

    /**
     * {@inheritdoc}
     */
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
        $ret = substr($this->response->getBody(), $this->position, $count);
        $this->position += \strlen($ret);

        return $ret;
    }

    /**
     * Write to stream.
     *
     * @throws \BadMethodCallException if called, because this method is not applicable for this stream
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-write.php
     *
     * @param string $data should be stored into the underlying stream
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
     *
     * @return int should return the current position of the stream
     */
    public function stream_tell(): int
    {
        return $this->position;
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-eof.php
     *
     * @return bool should return TRUE if the read/write position is at the end of the stream
     *              and if no more data is available to be read, or FALSE otherwise
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
            case SEEK_SET:
                if ($offset < \strlen($this->response->getBody()) && $offset >= 0) {
                    $this->position = $offset;

                    return true;
                }
                break;
            case SEEK_CUR:
                if ($offset >= 0) {
                    $this->position += $offset;

                    return true;
                }
                break;
            case SEEK_END:
                if (\strlen($this->response->getBody()) + $offset >= 0) {
                    $this->position = \strlen($this->response->getBody()) + $offset;

                    return true;
                }
        }

        return false;
    }

    /**
     * Change stream options.
     *
     * @see http://www.php.net/manual/en/streamwrapper.stream-metadata.php
     *
     * @param string $path   the file path or URL to set metadata
     * @param int    $option one of the stream options
     * @param mixed  $var    value depending on the option
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function stream_metadata(string $path, int $option, $var): bool
    {
        return false;
    }
}
