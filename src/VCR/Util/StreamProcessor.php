<?php

namespace VCR\Util;

use VCR\Configuration;
use VCR\CodeTransform\AbstractCodeTransform;

/**
 * Implementation adapted from:
 * https://github.com/antecedent/patchwork/blob/418a9aae80ca3228d6763a2dc6d9a30ade7a4e7e/lib/Preprocessor/Stream.php
 *
 * @author     Ignas Rudaitis <ignas.rudaitis@gmail.com>
 * @author     Adrian Philipp <mail@adrian-philipp.com>
 * @copyright  2010-2013 Ignas Rudaitis
 * @license    http://www.opensource.org/licenses/mit-license.html
 * @link       http://antecedent.github.com/patchwork
 */
class StreamProcessor
{
    /**
     * Constant for a stream which was opened while including a file.
     */
    const STREAM_OPEN_FOR_INCLUDE = 128;

    /**
     * Stream protocol which is used when registering this wrapper.
     */
    const PROTOCOL = 'file';

    /**
     * @var Configuration
     */
    protected static $configuration;

    /**
     * @var AbstractCodeTransform[] $codeTransformers Transformers which have been appended to this stream processor.
     */
    protected static $codeTransformers = array();

    /**
     * @var resource Resource for the currently opened file.
     */
    protected $resource;

    /**
     * @link http://www.php.net/manual/en/class.streamwrapper.php#streamwrapper.props.context
     * @var resource The current context, or NULL if no context was passed to the caller function.
     */
    public $context;

    /**
     * @var bool
     */
    protected $isIntercepting = false;

    /**
     *
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration = null)
    {
        if ($configuration) {
            static::$configuration = $configuration;
        }
    }

    /**
     * Registers current class as the PHP file stream wrapper.
     *
     * @return void
     */
    public function intercept()
    {
        if (!$this->isIntercepting) {
            stream_wrapper_unregister(self::PROTOCOL);
            $this->isIntercepting = stream_wrapper_register(self::PROTOCOL, __CLASS__);
        }
    }

    /**
     * Restores the original file stream wrapper status.
     *
     * @return void
     */
    public function restore()
    {
        stream_wrapper_restore(self::PROTOCOL);
    }

    /**
     * Determines that the provided url is member of a url whitelist.
     *
     * @param string $uri
     *
     * @return bool True if the specified url is whitelisted, false otherwise.
     */
    protected function isWhitelisted($uri)
    {
        $whiteList = static::$configuration->getWhiteList();

        if (empty($whiteList)) {
            return true;
        }

        $uri = $this->normalizePath($uri);

        foreach ($whiteList as $path) {
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
     * @return bool True if the provided url is blacklisted, false otherwise.
     */
    protected function isBlacklisted($uri)
    {
        $uri = $this->normalizePath($uri);

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

    /**
     * Opens a stream and attaches registered filters.
     *
     * @param  string  $path       Specifies the URL that was passed to the original function.
     * @param  string  $mode       The mode used to open the file, as detailed for fopen().
     * @param  integer $options    Holds additional flags set by the streams API. It can hold one or more of the following values OR'd together.
     * @param  string  $openedPath If the path is opened successfully, and STREAM_USE_PATH is set in options,
     *                             opened_path should be set to the full path of the file/resource that was
     *                             actually opened.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        $this->restore();

        if (isset($this->context)) {
            $this->resource = fopen($path, $mode, $options & STREAM_USE_PATH, $this->context);
        } else {
            $this->resource = fopen($path, $mode, $options & STREAM_USE_PATH);
        }

        if ($options & self::STREAM_OPEN_FOR_INCLUDE && $this->shouldProcess($path)) {
            $this->appendFiltersToStream($this->resource);
        }

        $this->intercept();

        return $this->resource !== false;
    }

    /**
     * Close an resource.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-close.php
     *
     * @return boolean
     */
    public function stream_close()
    {
        return fclose($this->resource);
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
        return feof($this->resource);
    }

    /**
     * Flushes the output.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-flush.php
     *
     * @return boolean
     */
    public function stream_flush()
    {
        return fflush($this->resource);
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
        return fread($this->resource, $count);
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
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->resource, $offset, $whence) === 0;
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
        return fstat($this->resource);
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
        return ftell($this->resource);
    }

    /**
     * Retrieve information about a file.
     *
     * @link http://www.php.net/manual/en/streamwrapper.url-stat.php
     *
     * @param  string  $path  The file path or URL to stat.
     * @param  integer $flags Holds additional flags set by the streams API.
     *
     * @return integer        Should return as many elements as stat() does.
     */
    public function url_stat($path, $flags)
    {
        $this->restore();
        if ($flags & STREAM_URL_STAT_QUIET) {
            set_error_handler(function() {
                // Use native error handler
                return false;
            });
            $result = @stat($path);
            restore_error_handler();
        } else {
            $result = stat($path);
        }
        $this->intercept();

        return $result;
    }

    /**
     * Close directory handle.
     *
     * @link http://www.php.net/manual/en/streamwrapper.dir-closedir.php
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function dir_closedir()
    {
        closedir($this->resource);

        return true;
    }

    /**
     * Open directory handle.
     *
     * @link http://www.php.net/manual/en/streamwrapper.dir-opendir.php
     *
     * @param  string $path The file path or URL to stat.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function dir_opendir($path)
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

    /**
     * Read entry from directory handle.
     *
     * @link http://www.php.net/manual/en/streamwrapper.dir-readdir.php
     *
     * @return mixed Should return string representing the next filename, or FALSE if there is no next file.
     */
    public function dir_readdir()
    {
        return readdir($this->resource);
    }

    /**
     * Rewind directory handle.
     *
     * @link http://www.php.net/manual/en/streamwrapper.dir-rewinddir.php
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function dir_rewinddir()
    {
        rewinddir($this->resource);

        return true;
    }

    /**
     * Create a directory.
     *
     * @link http://www.php.net/manual/en/streamwrapper.mkdir.php
     *
     * @param  string  $path       Directory which should be created.
     * @param  int     $mode       The value passed to mkdir().
     * @param  integer $options    A bitwise mask of values, such as STREAM_MKDIR_RECURSIVE.
     *
     * @return boolean  Returns TRUE on success or FALSE on failure.
     */
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

    /**
     * Renames a file or directory.
     *
     * @link http://www.php.net/manual/en/streamwrapper.rename.php
     *
     * @param  string $path_from The URL to the current file.
     * @param  string $path_to   The URL which the path_from should be renamed to.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
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

    /**
     * Removes a directory
     *
     * @link http://www.php.net/manual/en/streamwrapper.rmdir.php
     *
     * @param  string $path The directory URL which should be removed.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function rmdir($path)
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

    /**
     * Retrieve the underlaying resource.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-cast.php
     *
     * @param  integer $cast_as Can be STREAM_CAST_FOR_SELECT when stream_select() is calling stream_cast() or
     *                          STREAM_CAST_AS_STREAM when stream_cast() is called for other uses.
     * @return resource         Should return the underlying stream resource used by the wrapper, or FALSE.
     */
    public function stream_cast($cast_as)
    {
        return $this->resource;
    }

    /**
     * Advisory file locking.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-lock.php
     *
     * @param  integer $operation One of the operation constantes.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function stream_lock($operation)
    {
        return flock($this->resource, $operation);
    }

    /**
     * Change stream options.
     *
     * @codeCoverageIgnore
     *
     * @param  int $option One of STREAM_OPTION_BLOCKING, STREAM_OPTION_READ_TIMEOUT, STREAM_OPTION_WRITE_BUFFER.
     * @param  int $arg1   Depending on option.
     * @param  int $arg2   Depending on option.
     *
     * @return boolean Returns TRUE on success or FALSE on failure. If option is not implemented,
     *                 FALSE should be returned.
     */
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
        return fwrite($this->resource, $data);
    }

    /**
     * Delete a file.
     *
     * @link http://www.php.net/manual/en/streamwrapper.unlink.php
     *
     * @param  string $path The file URL which should be deleted.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
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

    /**
     * Change stream options.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-metadata.php
     * @param  string  $path   The file path or URL to set metadata.
     * @param  integer $option One of the stream options.
     * @param  mixed   $value  Value depending on the option.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function stream_metadata($path, $option, $value)
    {
        $this->restore();
        $result = null;

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

    /**
     * Truncate stream.
     *
     * @link http://www.php.net/manual/en/streamwrapper.stream-truncate.php
     *
     * @param  integer $new_size The new size.
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function stream_truncate($new_size)
    {
        return ftruncate($this->resource, $new_size);
    }

    /**
     * Adds code transformer to the stream processor.
     *
     * @param AbstractCodeTransform $codeTransformer
     *
     * @return void
     */
    public function appendCodeTransformer(AbstractCodeTransform $codeTransformer)
    {
        static::$codeTransformers[$codeTransformer::NAME] = $codeTransformer;
    }

    /**
     * Removes a code transformer from the stream processor.
     *
     * @param AbstractCodeTransform $codeTransformer
     *
     * @return void
     */
    public function detachCodeTransformer(AbstractCodeTransform $codeTransformer)
    {
        if (!empty(static::$codeTransformers[$codeTransformer::NAME])) {
            unset(static::$codeTransformers[$codeTransformer::NAME]);
        }
    }

    /**
     * Appends the current set of php_user_filter to the provided stream.
     *
     * @param resource $stream
     */
    protected function appendFiltersToStream($stream)
    {
        foreach (static::$codeTransformers as $codeTransformer) {
            stream_filter_append($stream, $codeTransformer::NAME, STREAM_FILTER_READ);
        }
    }

    /**
     * Normalizes the path, to always use the slash as directory separator.
     *
     * @param string $path
     *
     * @return string
     */
    private function normalizePath($path)
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            return str_replace(DIRECTORY_SEPARATOR, '/', $path);
        }

        return $path;
    }
}
