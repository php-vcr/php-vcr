<?php

namespace VCR\LibraryHooks\CurlRewrite;


require_once __DIR__ . '/DrupalLocalStreamWrapper.php';
require_once __DIR__ . '/Filter.php';

class Wrapper
{
    /**
     * @var DrupalLocalStreamWrapper
     */
    protected $wrapper;

    protected static $whitelistPaths = array();

    public static function interceptIncludes($whitelistPaths = null)
    {
        if (is_array($whitelistPaths)) {
            self::$whitelistPaths = $whitelistPaths;
        }
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', __CLASS__);
    }

    public static function restore()
    {
        stream_wrapper_restore('file');
    }

    public function __construct()
    {
        $this->wrapper = new DrupalLocalStreamWrapper();
    }

    /**
     * Wraps all calls to this stream wrapper .
     */
    public function __call($method, $args)
    {
        $strangeMethods = array('url_stat', 'dir_opendir');
        if (in_array($method, $strangeMethods)) {
            $this->wrapper = new DrupalLocalStreamWrapper();
        }

        $localMethod = str_replace('stream_', '', $method);
        if (method_exists($this, $localMethod)) {
            $callback = array($this, $localMethod);
        } else {
            $callback = array($this->wrapper, $method);
        }

        self::restore();
        $returnValue = call_user_func_array($callback, $args);
        self::interceptIncludes();

        return $returnValue;
    }

    public function open($uri, $mode, $options, &$opened_path)
    {
        $returnValue = $this->wrapper->stream_open($uri, $mode, $options, $opened_path);

        if ($this->isWhitelisted($uri) && $this->isPhpFile($uri)) {
            stream_filter_append($this->wrapper->handle, Filter::NAME, STREAM_FILTER_READ);
        }

        return $returnValue;
    }

    protected function isWhitelisted($uri)
    {
        foreach (self::$whitelistPaths as $path) {
            if (strpos($uri, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function isPhpFile($uri)
    {
        return pathinfo($uri, PATHINFO_EXTENSION) === 'php';
    }


}
