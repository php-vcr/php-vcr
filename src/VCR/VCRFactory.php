<?php

namespace VCR;

use VCR\LibraryHooks\CurlHook;
use VCR\LibraryHooks\SoapHook;
use VCR\Util\StreamProcessor;

class VCRFactory
{
    /**
     * @var Configuration
     **/
    protected $config;

    protected $mapping = array();

    protected static $instance;

    protected function __construct($config = null)
    {
        $this->config = $config ?: $this->getOrCreate('Configuration');
    }

    protected function createConfiguration()
    {
        return new Configuration();
    }

    /**
     * Provides an instance of the StreamProcessor.
     *
     * @return StreamProcessor
     */
    protected function createUtilStreamProcessor()
    {
        return new StreamProcessor($this->config);
    }

    /**
     * @return Videorecorder
     */
    protected function createVideorecorder()
    {
        return new Videorecorder(
            $this->getOrCreate('Configuration'),
            $this->getOrCreate('HttpClient'),
            $this
        );
    }

    protected function createHttpClient()
    {
        return new Util\HttpClient();
    }

    protected function createStorage($filePath)
    {
        $class = $this->config->getStorage();

        return new $class($filePath);
    }

    protected function createVCRLibraryHooksSoapHook()
    {
        return new SoapHook(
            $this->getOrCreate('VCR\Filter\SoapFilter'),
            $this->getOrCreate('Util\StreamProcessor')
        );
    }

    protected function createVCRLibraryHooksCurlHook()
    {
        return new CurlHook(
            $this->getOrCreate('VCR\Filter\CurlFilter'),
            $this->getOrCreate('Util\StreamProcessor')
        );
    }

    public static function getInstance($config = null)
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * @param string $className
     */
    public static function get($className, $params = array())
    {
        return self::getInstance()->getOrCreate($className, $params);
    }

    /**
     * @param string $className
     * @param array  $params
     *
     * @return mixed
     */
    public function getOrCreate($className, $params = array())
    {
        $key = $className . join('-', $params);

        if (isset($this->mapping[$key])) {
            return $this->mapping[$key];
        }

        if (method_exists($this, $this->getMethodName($className))) {
            $callback = array($this, $this->getMethodName($className));
            $instance =  call_user_func_array($callback, $params);
        } else {
            $instance = new $className;
        }

        return $this->mapping[$key] = $instance;
    }

    /**
     *
     * Example:
     *
     *   ClassName: \Tux\Foo\Linus
     *   Returns: createTuxFooLinus
     *
     * @param string $className
     *
     * @return string
     */
    protected function getMethodName($className)
    {
        return 'create' . str_replace('\\', '', $className);
    }
}
