<?php

namespace VCR\LibraryHooks\CurlRewrite;

/**
 * @file
 * Drupal stream wrapper interface.
 *
 * Provides a Drupal interface and classes to implement PHP stream wrappers for
 * public, private, and temporary files.
 *
 * A stream wrapper is an abstraction of a file system that allows Drupal to
 * use the same set of methods to access both local files and remote resources.
 *
 * Note that PHP 5.2 fopen() only supports URIs of the form "scheme://target"
 * despite the fact that according to RFC 3986 a URI's scheme component
 * delimiter is in general just ":", not "://".  Because of this PHP limitation
 * and for consistency Drupal will only accept URIs of form "scheme://target".
 *
 * @see http://www.faqs.org/rfcs/rfc3986.html
 * @see http://bugs.php.net/bug.php?id=47070
 */

/**
 * Drupal stream wrapper base class for local files.
 *
 * This class provides a complete stream wrapper implementation. URIs such as
 * "public://example.txt" are expanded to a normal filesystem path such as
 * "sites/default/files/example.txt" and then PHP filesystem functions are
 * invoked.
 *
 * DrupalLocalStreamWrapper implementations need to implement at least the
 * getDirectoryPath() and getExternalUrl() methods.
 */
class DrupalLocalStreamWrapper
{
  /**
   * Stream context resource.
   *
   * @var Resource
   */
  public $context;

  /**
   * A generic resource handle.
   *
   * @var Resource
   */
  public $handle = NULL;

  /**
   * Instance URI (stream).
   *
   * A stream is referenced as "scheme://target".
   *
   * @var String
   */
  protected $uri;

  /**
   * Base implementation of setUri().
   */
  function setUri($uri) {
    $this->uri = $uri;
  }

  /**
   * Base implementation of getUri().
   */
  function getUri() {
    return $this->uri;
  }

  /**
   * Returns the local writable target of the resource within the stream.
   *
   * This function should be used in place of calls to realpath() or similar
   * functions when attempting to determine the location of a file. While
   * functions like realpath() may return the location of a read-only file, this
   * method may return a URI or path suitable for writing that is completely
   * separate from the URI used for reading.
   *
   * @param $uri
   *   Optional URI.
   *
   * @return
   *   Returns a string representing a location suitable for writing of a file,
   *   or FALSE if unable to write to the file such as with read-only streams.
   */
  protected function getTarget($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }

    list($scheme, $target) = explode('://', $uri, 2);

    // Remove erroneous leading or trailing, forward-slashes and backslashes.
    return trim($target, '\/');
  }

  /**
   * Base implementation of getMimeType().
   */
  static function getMimeType($uri, $mapping = NULL) {
    if (!isset($mapping)) {
      // The default file map, defined in file.mimetypes.inc is quite big.
      // We only load it when necessary.
      include_once DRUPAL_ROOT . '/includes/file.mimetypes.inc';
      $mapping = file_mimetype_mapping();
    }

    $extension = '';
    $file_parts = explode('.', drupal_basename($uri));

    // Remove the first part: a full filename should not match an extension.
    array_shift($file_parts);

    // Iterate over the file parts, trying to find a match.
    // For my.awesome.image.jpeg, we try:
    //   - jpeg
    //   - image.jpeg, and
    //   - awesome.image.jpeg
    while ($additional_part = array_pop($file_parts)) {
      $extension = strtolower($additional_part . ($extension ? '.' . $extension : ''));
      if (isset($mapping['extensions'][$extension])) {
        return $mapping['mimetypes'][$mapping['extensions'][$extension]];
      }
    }

    return 'application/octet-stream';
  }

  /**
   * Base implementation of chmod().
   */
  function chmod($mode) {
    $output = @chmod($this->getLocalPath(), $mode);
    // We are modifying the underlying file here, so we have to clear the stat
    // cache so that PHP understands that URI has changed too.
    clearstatcache();
    return $output;
  }

  /**
   * Base implementation of realpath().
   */
  function realpath() {
    return $this->getLocalPath();
  }

  /**
   * Returns the canonical absolute path of the URI, if possible.
   *
   * @param string $uri
   *   (optional) The stream wrapper URI to be converted to a canonical
   *   absolute path. This may point to a directory or another type of file.
   *
   * @return string|false
   *   If $uri is not set, returns the canonical absolute path of the URI
   *   previously set by the DrupalStreamWrapperInterface::setUri() function.
   *   If $uri is set and valid for this class, returns its canonical absolute
   *   path, as determined by the realpath() function. If $uri is set but not
   *   valid, returns FALSE.
   */
  protected function getLocalPath($uri = NULL) {
    if (!isset($uri)) {
      $uri = $this->uri;
    }
    $realpath = realpath($uri);
    if (!$realpath) {
      return FALSE;
    }
    return $realpath;
  }

  /**
   * Support for fopen(), file_get_contents(), file_put_contents() etc.
   *
   * @param $uri
   *   A string containing the URI to the file to open.
   * @param $mode
   *   The file mode ("r", "wb" etc.).
   * @param $options
   *   A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
   * @param $opened_path
   *   A string containing the path actually opened.
   *
   * @return
   *   Returns TRUE if file was opened successfully.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-open.php
   */
  public function stream_open($uri, $mode, $options, &$opened_path) {
    $this->uri = $uri;
    $path = $this->getLocalPath();
    $this->handle = ($options & STREAM_REPORT_ERRORS) ? fopen($path, $mode) : @fopen($path, $mode);

    if ((bool) $this->handle && $options & STREAM_USE_PATH) {
      $opened_path = $path;
    }

    return (bool) $this->handle;
  }

  /**
   * Support for flock().
   *
   * @param $operation
   *   One of the following:
   *   - LOCK_SH to acquire a shared lock (reader).
   *   - LOCK_EX to acquire an exclusive lock (writer).
   *   - LOCK_UN to release a lock (shared or exclusive).
   *   - LOCK_NB if you don't want flock() to block while locking (not
   *     supported on Windows).
   *
   * @return
   *   Always returns TRUE at the present time.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-lock.php
   */
  public function stream_lock($operation) {
    if (in_array($operation, array(LOCK_SH, LOCK_EX, LOCK_UN, LOCK_NB))) {
      return flock($this->handle, $operation);
    }

    return TRUE;
  }

  /**
   * Support for fread(), file_get_contents() etc.
   *
   * @param $count
   *   Maximum number of bytes to be read.
   *
   * @return
   *   The string that was read, or FALSE in case of an error.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-read.php
   */
  public function stream_read($count) {
    return fread($this->handle, $count);
  }

  /**
   * Support for fwrite(), file_put_contents() etc.
   *
   * @param $data
   *   The string to be written.
   *
   * @return
   *   The number of bytes written (integer).
   *
   * @see http://php.net/manual/en/streamwrapper.stream-write.php
   */
  public function stream_write($data) {
    return fwrite($this->handle, $data);
  }

  /**
   * Support for feof().
   *
   * @return
   *   TRUE if end-of-file has been reached.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-eof.php
   */
  public function stream_eof() {
    return feof($this->handle);
  }

  /**
   * Support for fseek().
   *
   * @param $offset
   *   The byte offset to got to.
   * @param $whence
   *   SEEK_SET, SEEK_CUR, or SEEK_END.
   *
   * @return
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-seek.php
   */
  public function stream_seek($offset, $whence) {
    // fseek returns 0 on success and -1 on a failure.
    // stream_seek   1 on success and  0 on a failure.
    return !fseek($this->handle, $offset, $whence);
  }

  /**
   * Support for fflush().
   *
   * @return
   *   TRUE if data was successfully stored (or there was no data to store).
   *
   * @see http://php.net/manual/en/streamwrapper.stream-flush.php
   */
  public function stream_flush() {
    return fflush($this->handle);
  }

  /**
   * Support for ftell().
   *
   * @return
   *   The current offset in bytes from the beginning of file.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-tell.php
   */
  public function stream_tell() {
    return ftell($this->handle);
  }

  /**
   * Support for fstat().
   *
   * @return
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-stat.php
   */
  public function stream_stat() {
    return fstat($this->handle);
  }

  /**
   * Support for fclose().
   *
   * @return
   *   TRUE if stream was successfully closed.
   *
   * @see http://php.net/manual/en/streamwrapper.stream-close.php
   */
  public function stream_close() {
    return fclose($this->handle);
  }

  /**
   * Support for unlink().
   *
   * @param $uri
   *   A string containing the URI to the resource to delete.
   *
   * @return
   *   TRUE if resource was successfully deleted.
   *
   * @see http://php.net/manual/en/streamwrapper.unlink.php
   */
  public function unlink($uri) {
    $this->uri = $uri;
    return drupal_unlink($this->getLocalPath());
  }

  /**
   * Support for rename().
   *
   * @param $from_uri,
   *   The URI to the file to rename.
   * @param $to_uri
   *   The new URI for file.
   *
   * @return
   *   TRUE if file was successfully renamed.
   *
   * @see http://php.net/manual/en/streamwrapper.rename.php
   */
  public function rename($from_uri, $to_uri) {
    return rename($this->getLocalPath($from_uri), $this->getLocalPath($to_uri));
  }

  /**
   * Gets the name of the directory from a given path.
   *
   * This method is usually accessed through drupal_dirname(), which wraps
   * around the PHP dirname() function because it does not support stream
   * wrappers.
   *
   * @param $uri
   *   A URI or path.
   *
   * @return
   *   A string containing the directory name.
   *
   * @see drupal_dirname()
   */
  public function dirname($uri = NULL) {
    list($scheme, $target) = explode('://', $uri, 2);
    $target  = $this->getTarget($uri);
    $dirname = dirname($target);

    if ($dirname == '.') {
      $dirname = '';
    }

    return $scheme . '://' . $dirname;
  }

  /**
   * Support for mkdir().
   *
   * @param $uri
   *   A string containing the URI to the directory to create.
   * @param $mode
   *   Permission flags - see mkdir().
   * @param $options
   *   A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE.
   *
   * @return
   *   TRUE if directory was successfully created.
   *
   * @see http://php.net/manual/en/streamwrapper.mkdir.php
   */
  public function mkdir($uri, $mode, $options) {
    $this->uri = $uri;
    $recursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
    if ($recursive) {
      // $this->getLocalPath() fails if $uri has multiple levels of directories
      // that do not yet exist.
      $localpath = $this->getDirectoryPath() . '/' . $this->getTarget($uri);
    }
    else {
      $localpath = $this->getLocalPath($uri);
    }
    if ($options & STREAM_REPORT_ERRORS) {
      return mkdir($localpath, $mode, $recursive);
    }
    else {
      return @mkdir($localpath, $mode, $recursive);
    }
  }

  /**
   * Support for rmdir().
   *
   * @param $uri
   *   A string containing the URI to the directory to delete.
   * @param $options
   *   A bit mask of STREAM_REPORT_ERRORS.
   *
   * @return
   *   TRUE if directory was successfully removed.
   *
   * @see http://php.net/manual/en/streamwrapper.rmdir.php
   */
  public function rmdir($uri, $options) {
    $this->uri = $uri;
    if ($options & STREAM_REPORT_ERRORS) {
      return drupal_rmdir($this->getLocalPath());
    }
    else {
      return @drupal_rmdir($this->getLocalPath());
    }
  }

  /**
   * Support for stat().
   *
   * @param $uri
   *   A string containing the URI to get information about.
   * @param $flags
   *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
   *
   * @return
   *   An array with file status, or FALSE in case of an error - see fstat()
   *   for a description of this array.
   *
   * @see http://php.net/manual/en/streamwrapper.url-stat.php
   */
  public function url_stat($uri, $flags) {
    $this->uri = $uri;
    $path = $this->getLocalPath();
    // Suppress warnings if requested or if the file or directory does not
    // exist. This is consistent with PHP's plain filesystem stream wrapper.
    if ($flags & STREAM_URL_STAT_QUIET || !file_exists($path)) {
      return @stat($path);
    }
    else {
      return stat($path);
    }
  }

  /**
   * Support for opendir().
   *
   * @param $uri
   *   A string containing the URI to the directory to open.
   * @param $options
   *   Unknown (parameter is not documented in PHP Manual).
   *
   * @return
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-opendir.php
   */
  public function dir_opendir($uri, $options) {
    $this->uri = $uri;
    $this->handle = opendir($this->getLocalPath());

    return (bool) $this->handle;
  }

  /**
   * Support for readdir().
   *
   * @return
   *   The next filename, or FALSE if there are no more files in the directory.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-readdir.php
   */
  public function dir_readdir() {
    return readdir($this->handle);
  }

  /**
   * Support for rewinddir().
   *
   * @return
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-rewinddir.php
   */
  public function dir_rewinddir() {
    rewinddir($this->handle);
    // We do not really have a way to signal a failure as rewinddir() does not
    // have a return value and there is no way to read a directory handler
    // without advancing to the next file.
    return TRUE;
  }

  /**
   * Support for closedir().
   *
   * @return
   *   TRUE on success.
   *
   * @see http://php.net/manual/en/streamwrapper.dir-closedir.php
   */
  public function dir_closedir() {
    closedir($this->handle);
    // We do not really have a way to signal a failure as closedir() does not
    // have a return value.
    return TRUE;
  }
}
