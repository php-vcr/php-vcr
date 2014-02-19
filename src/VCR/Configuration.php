<?php

namespace VCR;

/**
 * Configuration.
 */
class Configuration
{
    private $cassettePath = 'tests/fixtures';

    // All are enabled by default
    private $enabledLibraryHooks = array('stream_wrapper', 'curl_rewrite', 'soap');
    private $availableLibraryHooks = array(
        'stream_wrapper' => 'VCR\LibraryHooks\StreamWrapper',
        'curl_runkit'    => 'VCR\LibraryHooks\CurlRunkit',
        'curl_rewrite'   => 'VCR\LibraryHooks\CurlRewrite',
        'soap'           => 'VCR\LibraryHooks\Soap',
    );

    // Yaml by default
    private $enabledStorage = 'yaml';
    private $availableStorages = array(
        'json' => 'VCR\Storage\Json',
        'yaml' => 'VCR\Storage\Yaml',
    );

    // All are enabled by default
    private $enabledRequestMatchers;
    private $availableRequestMatchers = array(
        'method'       => array('VCR\RequestMatcher', 'matchMethod'),
        'url'          => array('VCR\RequestMatcher', 'matchUrl'),
        'host'         => array('VCR\RequestMatcher', 'matchHost'),
        'headers'      => array('VCR\RequestMatcher', 'matchHeaders'),
        'body'         => array('VCR\RequestMatcher', 'matchBody'),
        'post_fields'  => array('VCR\RequestMatcher', 'matchPostFields'),
        'query_string' => array('VCR\RequestMatcher', 'matchQueryString'),
    );
    private $whiteList = array();
    private $blackList = array('src/VCR/LibraryHooks/', 'src/VCR/Util/Soap/SoapClient');

    /**
     *
     * @return array
     */
    public function getBlackList()
    {
        return $this->blackList;
    }

    /**
     * @param string|array $paths
     * @return $this
     */
    public function setBlackList($paths)
    {
        $paths = (is_array($paths)) ? $paths : array($paths);

        $this->blackList = $paths;

        return $this;

    }

    /**
     * @return string
     */
    public function getCassettePath()
    {
        return $this->cassettePath;
    }

    /**
     * @param string $cassettePath
     *
     * @return $this
     */
    public function setCassettePath($cassettePath)
    {
        Assertion::directory($cassettePath, "Cassette path '{$cassettePath}' doesn't exist.");
        $this->cassettePath = $cassettePath;

        return $this;
    }

    public function getLibraryHooks()
    {
        if (is_null($this->enabledLibraryHooks)) {
            return array_values($this->availableLibraryHooks);
        }

        return array_values(array_intersect_key(
            $this->availableLibraryHooks,
            array_flip($this->enabledLibraryHooks)
        ));
    }

    public function enableLibraryHooks($hooks)
    {
        $hooks = is_array($hooks) ? $hooks : array($hooks);
        $invalidHooks = array_diff($hooks, array_keys($this->availableLibraryHooks));
        if ($invalidHooks) {
            throw new \InvalidArgumentException("Library hooks don't exist: " . join(', ', $invalidHooks));
        }
        $this->enabledLibraryHooks = $hooks;

        return $this;
    }

    public function getStorage()
    {
        return $this->availableStorages[$this->enabledStorage];
    }

    public function getRequestMatchers()
    {
        if (is_null($this->enabledRequestMatchers)) {
            return array_values($this->availableRequestMatchers);
        }

        return array_values(array_intersect_key(
            $this->availableRequestMatchers,
            array_flip($this->enabledRequestMatchers)
        ));
    }

    public function addRequestMatcher($name, $callback)
    {
        Assertion::minLength($name, 1, "A request matchers name must be at least one character long. Found '{$name}'");
        Assertion::isCallable($callback, "Request matcher '{$name}' is not callable.");
        $this->availableRequestMatchers[$name] = $callback;

        return $this;
    }

    public function enableRequestMatchers(array $matchers)
    {
        $invalidMatchers = array_diff($matchers, array_keys($this->availableRequestMatchers));
        if ($invalidMatchers) {
            throw new \InvalidArgumentException("Request matchers don't exist: " . join(', ', $invalidMatchers));
        }
        $this->enabledRequestMatchers = $matchers;
    }

    public function setStorage($storageName)
    {
        Assertion::inArray($storageName, $this->availableStorages, "Storage '{$storageName}' not available.");
        $this->enabledStorage = $storageName;

        return $this;
    }

    /**
     * Provides a former defined class paths white list.
     * @return array
     */
    public function getWhiteList()
    {
        return $this->whiteList;
    }

    /**
     * Defines a set of relative class file paths.
     *
     * @param string|array $paths Set of relative paths to a class file the class should be
     * @return $this
     */
    public function setWhiteList($paths)
    {
        $paths = (is_array($paths)) ? $paths : array($paths);

        $this->whiteList = $paths;

        return $this;
    }
}
