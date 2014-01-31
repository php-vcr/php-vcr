<?php

namespace VCR\LibraryHooks;

use VCR\Request;
use VCR\Response;
use VCR\Util\Assertion;

/**
 * StreamWrapper.
 */
class StreamWrapper implements LibraryHook
{
    private static $requestCallback;

    private $position;

    /**
     * @var Response
     */
    private $response;

    public function __construct()
    {
    }

    public function enable(\Closure $requestCallback)
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');
        self::$requestCallback = $requestCallback;
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', __CLASS__, STREAM_IS_URL);

        stream_wrapper_unregister('https');
        stream_wrapper_register('https', __CLASS__, STREAM_IS_URL);
    }

    public function disable()
    {
        self::$requestCallback = null;
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');
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

    public function stream_seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < strlen($this->response->getBody()) && $offset >= 0) {
                     $this->position = $offset;

                     return true;
                } else {
                     return false;
                }
                break;

            case SEEK_CUR:
                if ($offset >= 0) {
                     $this->position += $offset;

                     return true;
                } else {
                     return false;
                }
                break;

            case SEEK_END:
                if (strlen($this->response->getBody()) + $offset >= 0) {
                     $this->position = strlen($this->response->getBody()) + $offset;

                     return true;
                } else {
                     return false;
                }
                break;

            default:
                return false;
        }
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
