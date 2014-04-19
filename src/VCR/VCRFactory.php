<?php

namespace VCR;

class VCRFactory
{
    /**
     * @var Configuration
     **/
    protected $config;

    protected $mapping = array();

    protected static $instance;

   /**
    * Creates a new VCRFactory instance.
    *
    * @param Configuration $config
    */
    protected function __construct($config = null)
    {
        $this->config = $config ?: $this->getOrCreate('VCR\Configuration');
    }

    /**
     * @return Videorecorder
     */
    protected function createVCRVideorecorder()
    {
        return new Videorecorder(
            $this->config,
            $this->getOrCreate('VCR\Util\HttpClient'),
            $this
        );
    }

    /**
     * Provides an instance of the StreamProcessor.
     *
     * @return \VCR\Util\StreamProcessor
     */
    protected function createVCRUtilStreamProcessor()
    {
        return new Util\StreamProcessor($this->config);
    }

    protected function createStorage($cassetteName)
    {
        $dsn = $this->config->getCassettePath();
        $class = $this->config->getStorage();

        return new $class($dsn, $cassetteName);
    }

    protected function createVCRLibraryHooksSoapHook()
    {
        return new LibraryHooks\SoapHook(
            $this->getOrCreate('VCR\CodeTransform\SoapCodeTransform'),
            $this->getOrCreate('VCR\Util\StreamProcessor')
        );
    }

    protected function createVCRLibraryHooksCurlHook()
    {
        return new LibraryHooks\CurlHook(
            $this->getOrCreate('VCR\CodeTransform\CurlCodeTransform'),
            $this->getOrCreate('VCR\Util\StreamProcessor')
        );
    }

    /**
     * Returns the same VCRFactory instance on ever call (singleton).
     *
     * @param  Configuration $config (Optional) configuration.
     *
     * @return VCRFactory
     */
    public static function getInstance(Configuration $config = null)
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Returns an instance for specified class name and parameters.
     *
     * @param string $className Class name to get a instance for.
     * @param array $params Constructor arguments for this class.
     *
     * @return mixed An instance for specified class name and parameters.
     */
    public static function get($className, $params = array())
    {
        return self::getInstance()->getOrCreate($className, $params);
    }

    /**
     * Returns an instance for specified classname and parameters.
     *
     * @param string $className Class name to get a instance for.
     * @param array $params Constructor arguments for this class.
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
