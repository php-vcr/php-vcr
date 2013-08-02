<?php

namespace VCR\LibraryHooks\CurlRewrite;

require_once __DIR__ . '/Filter.php';


/**
 * Implementation from:
 * https://github.com/antecedent/patchwork/blob/418a9aae80ca3228d6763a2dc6d9a30ade7a4e7e/lib/Preprocessor/Stream.php
 *
 * @author     Ignas Rudaitis <ignas.rudaitis@gmail.com>
 * @copyright  2010-2013 Ignas Rudaitis
 * @license    http://www.opensource.org/licenses/mit-license.html
 * @link       http://antecedent.github.com/patchwork
 */
class Wrapper
{
    const STREAM_OPEN_FOR_INCLUDE = 128;

    protected static $whitelistPaths = array();
    protected static $blacklistPaths = array();

    public static function interceptIncludes($whitelistPaths = null, $blacklistPaths = null)
    {
        if (is_array($whitelistPaths)) {
            self::$whitelistPaths = $whitelistPaths;
        }

        if (is_array($blacklistPaths)) {
            self::$blacklistPaths = $blacklistPaths;
        }
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', __CLASS__);
    }

    public static function restore()
    {
        stream_wrapper_restore('file');
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

    protected function isBlacklisted($uri)
    {
        foreach (self::$blacklistPaths as $path) {
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

    protected function shouldProcess($uri)
    {
        return $this->isWhitelisted($uri) && !$this->isBlacklisted($uri) && $this->isPhpFile($uri);
    }

    public function stream_open($path, $mode, $options, &$openedPath)
    {
        self::restore();

        if (isset($this->context)) {
            $this->resource = fopen($path, $mode, $options, $this->context);
        } else {
            $this->resource = fopen($path, $mode, $options);
        }

        if ($options & self::STREAM_OPEN_FOR_INCLUDE && $this->shouldProcess($path)) {
            stream_filter_append($this->resource, Filter::NAME, STREAM_FILTER_READ);
        }

        self::interceptIncludes();
        return $this->resource !== false;
    }

    public function stream_close()
    {
        return fclose($this->resource);
    }

    public function stream_eof()
    {
        return feof($this->resource);
    }

    public function stream_flush()
    {
        return fflush($this->resource);
    }

    public function stream_read($count)
    {
        return fread($this->resource, $count);
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->resource, $offset, $whence) === 0;
    }

    public function stream_stat()
    {
        return fstat($this->resource);
    }

    public function stream_tell()
    {
        return ftell($this->resource);
    }

    public function url_stat($path, $flags)
    {
        self::restore();
        $result = @stat($path);
        self::interceptIncludes();
        return $result;
    }

    public function dir_closedir()
    {
        closedir($this->resource);
        return true;
    }

    public function dir_opendir($path, $options)
    {
        self::restore();
        if (isset($this->context)) {
            $this->resource = opendir($path, $this->context);
        } else {
            $this->resource = opendir($path);
        }
        self::interceptIncludes();
        return $this->resource !== false;
    }

    public function dir_readdir()
    {
        return readdir($this->resource);
    }

    public function dir_rewinddir()
    {
        rewinddir($this->resource);
        return true;
    }

    public function mkdir($path, $mode, $options)
    {
        self::restore();
        if (isset($this->context)) {
            $result = mkdir($path, $mode, $options, $this->context);
        } else {
            $result = mkdir($path, $mode, $options);
        }
        self::interceptIncludes();
        return $result;
    }

    public function rename($path_from, $path_to)
    {
        self::restore();
        if (isset($this->context)) {
            $result = rename($path_from, $path_to, $this->context);
        } else {
            $result = rename($path_from, $path_to);
        }
        self::interceptIncludes();
        return $result;
    }

    public function rmdir($path, $options)
    {
        self::restore();
        if (isset($this->context)) {
            $result = rmdir($path, $this->context);
        } else {
            $result = rmdir($path);
        }
        self::interceptIncludes();
        return $result;
    }

    public function stream_cast($cast_as)
    {
        return $this->resource;
    }

    public function stream_lock($operation)
    {
        return flock($this->resource, $operation);
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return stream_set_blocking($this->resource, $arg1);
            case STREAM_OPTION_READ_TIMEOUT:
                return stream_set_timeout($this->resource, $arg1, $arg2);
            case STREAM_OPTION_WRITE_BUFFER:
                return stream_set_write_buffer($this->resource, $arg1);
            case STREAM_OPTION_READ_BUFFER:
                return stream_set_read_buffer($this->resource, $arg1);
            case STREAM_OPTION_CHUNK_SIZE:
                return stream_set_chunk_size($this->resource, $arg1);
        }
    }

    public function stream_write($data)
    {
        return fwrite($this->resource, $data);
    }

    public function unlink($path)
    {
        self::restore();
        if (isset($this->context)) {
            $result = unlink($path, $this->context);
        } else {
            $result = unlink($path);
        }
        self::interceptIncludes();
        return $result;
    }

    public function stream_metadata($path, $option, $value)
    {
        self::restore();
        switch ($option) {
            case STREAM_META_TOUCH:
                if (empty($value)) {
                    $result = touch($path);
                } else {
                    $result = touch($path, $value[0], $value[1]);
                }
                break;
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                $result = chown($path, $value);
                break;
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                $result = chgrp($path, $value);
                break;
            case STREAM_META_ACCESS:
                $result = chmod($path, $value);
                break;
        }
        self::interceptIncludes();
        return $result;
    }

    public function stream_truncate($new_size)
    {
        return ftruncate($this->resource, $new_size);
    }
}
