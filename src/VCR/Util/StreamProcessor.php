<?php

namespace VCR\Util;

use VCR\Configuration;
use VCR\LibraryHooks\FilterInterface;


/**
 * Implementation from:
 * https://github.com/antecedent/patchwork/blob/418a9aae80ca3228d6763a2dc6d9a30ade7a4e7e/lib/Preprocessor/Stream.php
 *
 * @author     Ignas Rudaitis <ignas.rudaitis@gmail.com>
 * @copyright  2010-2013 Ignas Rudaitis
 * @license    http://www.opensource.org/licenses/mit-license.html
 * @link       http://antecedent.github.com/patchwork
 */
class StreamProcessor
{
    const STREAM_OPEN_FOR_INCLUDE = 128;

    /**
     * @var Configuration
     */
    protected static $configuration;

    /** @var FilterInterface[] $filters */
    protected $filters = array();

    /**
     * @var bool
     */
    protected $isIntercepting = false;

    /**
     *
     * @param Configuration $configuration
     */
    public function __construct($configuration = null)
    {
        if ($configuration) {
            static::$configuration = $configuration;
        }
    }

    /**
     * Registers current class as the PHP file stream wrapper.
     */
    public function intercept()
    {
        if (!$this->isIntercepting) {

            $this->isIntercepting = true;

            stream_wrapper_unregister('file');
            stream_wrapper_register('file', __CLASS__);
        }
    }

    /**
     * Restores the original file stream wrapper status.
     */
    public function restore()
    {
        stream_wrapper_restore('file');
    }

    /**
     * Determines that the provided url is member of a url whitelist.
     *
     * @param string $uri
     *
     * @return bool
     */
    protected function isWhitelisted($uri)
    {
        foreach (static::$configuration->getWhiteList() as $path) {
            if (strpos($uri, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines that the provided url is member of a url blacklist.
     *
     * @param string $uri
     *
     * @return bool
     */
    protected function isBlacklisted($uri)
    {
        foreach (static::$configuration->getBlackList() as $path) {
            if (strpos($uri, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines that the provided uri leads to a PHP file.
     *
     * @todo What about PHP files with extensions:
     *   - .inc
     *   - .php(\d)*
     *   - ...
     *
     * @param string $uri
     *
     * @return bool
     */
    protected function isPhpFile($uri)
    {
        return pathinfo($uri, PATHINFO_EXTENSION) === 'php';
    }

    /**
     *
     * @param string $uri
     *
     * @return bool
     */
    protected function shouldProcess($uri)
    {
        return $this->isWhitelisted($uri) && !$this->isBlacklisted($uri) && $this->isPhpFile($uri);
    }

    public function stream_open($path, $mode, $options, &$openedPath)
    {
        $this->restore();

        if (isset($this->context)) {
            $this->resource = fopen($path, $mode, $options, $this->context);
        } else {
            $this->resource = fopen($path, $mode, $options);
        }

        if ($options & self::STREAM_OPEN_FOR_INCLUDE && $this->shouldProcess($path)) {
            $this->appendFiltersToStream($this->resource);
        }

        $this->intercept();
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
        $this->restore();
        $result = @stat($path);
        $this->intercept();
        return $result;
    }

    public function dir_closedir()
    {
        closedir($this->resource);
        return true;
    }

    public function dir_opendir($path, $options)
    {
        $this->restore();
        if (isset($this->context)) {
            $this->resource = opendir($path, $this->context);
        } else {
            $this->resource = opendir($path);
        }
        $this->intercept();
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
        $this->restore();
        if (isset($this->context)) {
            $result = mkdir($path, $mode, $options, $this->context);
        } else {
            $result = mkdir($path, $mode, $options);
        }
        $this->intercept();
        return $result;
    }

    public function rename($path_from, $path_to)
    {
        $this->restore();
        if (isset($this->context)) {
            $result = rename($path_from, $path_to, $this->context);
        } else {
            $result = rename($path_from, $path_to);
        }
        $this->intercept();
        return $result;
    }

    public function rmdir($path, $options)
    {
        $this->restore();
        if (isset($this->context)) {
            $result = rmdir($path, $this->context);
        } else {
            $result = rmdir($path);
        }
        $this->intercept();
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
        $this->restore();
        if (isset($this->context)) {
            $result = unlink($path, $this->context);
        } else {
            $result = unlink($path);
        }
        $this->intercept();
        return $result;
    }

    public function stream_metadata($path, $option, $value)
    {
        $this->restore();
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
        $this->intercept();
        return $result;
    }

    public function stream_truncate($new_size)
    {
        return ftruncate($this->resource, $new_size);
    }

    /**
     * @param FilterInterface $filter
     */
    public function appendFilter(FilterInterface $filter)
    {
        $this->filters[$filter::NAME] = $filter;
    }

    /**
     * @param FilterInterface $filter
     */
    public function detachFilter(FilterInterface $filter)
    {
        if (!empty($this->filters[$filter::NAME])) {
            unset($this->filters[$filter::NAME]);
        }
    }

    /**
     * Appends the current set of php_user_filter to the provided stream.
     *
     * @param resource $stream
     */
    protected function appendFiltersToStream($stream)
    {
        foreach ($this->filters as $filter) {

            stream_filter_append($stream, $filter::NAME, STREAM_FILTER_READ);
        }
    }
}
