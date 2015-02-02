<?php

namespace VCR\LibraryHooks;

use VCR\Request;
use VCR\Response;
use VCR\Util\Assertion;
use VCR\Util\HttpUtil;
use VCR\Util\StreamHelper;

/**
 * Library hook for streamWrapper functions using stream_wrapper_register().
 */
class StreamWrapperHook implements LibraryHook
{
    /**
     * @var \Closure Callback which will be executed when a request is intercepted.
     */
    protected static $requestCallback;

    /**
     * @var integer Position in the current response body.
     */
    protected $position;

    /**
     * @var string Current status of this hook, either enabled or disabled.
     */
    protected $status = self::DISABLED;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var resource Current stream context.
     */
    public $context;

    /**
     * @inheritDoc
     */
    public function enable(\Closure $requestCallback)
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
     * @inheritDoc
     */
    public function disable()
    {
        self::$requestCallback = null;
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');

        $this->status = self::DISABLED;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled()
    {
        return $this->status == self::ENABLED;
    }

    /**
     * This method is called immediately after the wrapper is initialized (f.e. by fopen() and file_get_contents()).
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-open.php
     * @param  string $path        Specifies the URL that was passed to the original function.
     * @param  string $mode        The mode used to open the file, as detailed for fopen().
     * @param  int $options        Holds additional flags set by the streams API.
     * @param  string $opened_path If the path is opened successfully, and STREAM_USE_PATH is set.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $request = StreamHelper::createRequestFromStreamContext($this->context, $path);

        $requestCallback = self::$requestCallback;
        $this->response = $requestCallback($request);

        return true;
    }

    /**
     * Read from stream.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-read.php
     * @param  int $count How many bytes of data from the current position should be returned.
     *
     * @return string If there are less than count bytes available, return as many as are available.
     *                If no more data is available, return either FALSE or an empty string.
     */
    public function stream_read($count)
    {
        $ret = substr($this->response->getBody(), $this->position, $count);
        $this->position += strlen($ret);

        return $ret;
    }

    /**
     * Write to stream.
     *
     * @throws \BadMethodCallException If called, because this method is not applicable for this stream.
     * @link http://www.php.net/manual/en/streamwrapper.stream-write.php
     *
     * @param  string $data Should be stored into the underlying stream.
     *
     * @return int
     */
    public function stream_write($data)
    {
        throw new \BadMethodCallException('No writing possible');
    }

    /**
     * Retrieve the current position of a stream.
     *
     * This method is called in response to fseek() to determine the current position.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-tell.php
     *
     * @return integer Should return the current position of the stream.
     */
    public function stream_tell()
    {
        return $this->position;
    }

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-eof.php
     *
     * @return boolean Should return TRUE if the read/write position is at the end of the stream
     *                 and if no more data is available to be read, or FALSE otherwise.
     */
    public function stream_eof()
    {
        return $this->position >= strlen($this->response->getBody());
    }

    /**
     * Retrieve information about a file resource.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-stat.php
     *
     * @return array See stat().
     */
    public function stream_stat()
    {
        return array();
    }

     /**
     * Retrieve information about a file resource.
     *
     * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
     *
     * @return array See stat().
     */

    public function url_stat($path,$flags)
    {
        return array();
    }

    /**
     * Seeks to specific location in a stream.
     *
     * @param  integer $offset The stream offset to seek to.
     * @param  integer $whence Possible values:
     *                         SEEK_SET - Set position equal to offset bytes.
     *                         SEEK_CUR - Set position to current location plus offset.
     *                         SEEK_END - Set position to end-of-file plus offset.
     * @return boolean Return TRUE if the position was updated, FALSE otherwise.
     */
    public function stream_seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < strlen($this->response->getBody()) && $offset >= 0) {
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
                if (strlen($this->response->getBody()) + $offset >= 0) {
                     $this->position = strlen($this->response->getBody()) + $offset;

                     return true;
                }
        }

        return false;
    }

    /**
     * Change stream options.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-metadata.php
     * @param  string  $path   The file path or URL to set metadata.
     * @param  integer $option One of the stream options.
     * @param  mixed   $var    Value depending on the option.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function stream_metadata($path, $option, $var)
    {
        return false;
    }
}
