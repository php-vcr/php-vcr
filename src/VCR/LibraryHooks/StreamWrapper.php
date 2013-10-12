<?php

namespace VCR\LibraryHooks;

use VCR\Configuration;
use VCR\Request;
use VCR\Response;
use VCR\Assertion;

/**
 * StreamWrapper.
 */
class StreamWrapper implements LibraryHookInterface
{
    private static $handleRequestCallback;

    private $position;

    /**
     * @var Response
     */
    private $response;

    public function __construct()
    {
    }

    public function enable(\Closure $handleRequestCallback)
    {
        Assertion::isCallable($handleRequestCallback, 'No valid callback for handling requests defined.');
        self::$handleRequestCallback = $handleRequestCallback;
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', __CLASS__, STREAM_IS_URL);

        stream_wrapper_unregister('https');
        stream_wrapper_register('https', __CLASS__, STREAM_IS_URL);
    }

    public function disable()
    {
        self::$handleRequestCallback = null;
        stream_wrapper_restore('http');
        stream_wrapper_restore('https');

    }

    function stream_open($path, $mode, $options, &$opened_path)
    {
        $handleRequestCallback = self::$handleRequestCallback;
        $this->response = $handleRequestCallback(new Request('GET', $path));

        return (string) $this->response->getBody();
    }

    function stream_read($count)
    {
        $ret = substr($this->response->getBody(), $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    function stream_write($data)
    {
        throw new \BadMethodCall('No writing possible');
    }

    function stream_tell()
    {
        return $this->position;
    }

    function stream_eof()
    {
        return $this->position >= strlen($this->response->getBody());
    }

    public function stream_stat()
    {
        return array();
    }

    function stream_seek($offset, $whence)
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

    function stream_metadata($path, $option, $var)
    {
        return false;
    }

    public function __destruct()
    {
        self::$handleRequestCallback = null;
    }
}