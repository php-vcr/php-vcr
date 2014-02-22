<?php

namespace VCR\LibraryHooks;

use VCR\Request;
use VCR\Response;
use VCR\Util\Assertion;

/**
 * StreamWrapper.
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

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $requestCallback = self::$requestCallback;
        $this->response = $requestCallback(new Request('GET', $path));

        return (string) $this->response->getBody();
    }

    public function stream_read($count)
    {
        $ret = substr($this->response->getBody(), $this->position, $count);
        $this->position += strlen($ret);

        return $ret;
    }

    public function stream_write($data)
    {
        throw new \BadMethodCall('No writing possible');
    }

    public function stream_tell()
    {
        return $this->position;
    }

    public function stream_eof()
    {
        return $this->position >= strlen($this->response->getBody());
    }

    public function stream_stat()
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

    public function stream_metadata($path, $option, $var)
    {
        return false;
    }

    public function __destruct()
    {
        self::$requestCallback = null;
    }
}
