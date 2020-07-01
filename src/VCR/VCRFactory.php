<?php

namespace VCR;

use VCR\LibraryHooks\CurlHook;
use VCR\LibraryHooks\SoapHook;
use VCR\Storage\Storage;
use VCR\Util\StreamProcessor;

class VCRFactory
{
    /**
     * @var Configuration
     **/
    protected $config;

    /**
     * @var array<string, object>
     */
    protected $mapping = [];

    /**
     * @var self|null
     */
    protected static $instance;

    /**
     * Creates a new VCRFactory instance.
     *
     * @param Configuration $config
     */
    protected function __construct(Configuration $config = null)
    {
        $this->config = $config ?: $this->getOrCreate('VCR\Configuration');
    }

    protected function createVCRVideorecorder(): Videorecorder
    {
        return new Videorecorder(
            $this->config,
            $this->getOrCreate('VCR\Util\HttpClient'),
            $this
        );
    }

    /**
     * Provides an instance of the StreamProcessor.
     */
    protected function createVCRUtilStreamProcessor(): StreamProcessor
    {
        return new StreamProcessor($this->config);
    }

    /** @return Storage<array> */
    protected function createStorage(string $cassetteName): Storage
    {
        $dsn = $this->config->getCassettePath();
        $class = $this->config->getStorage();

        return new $class($dsn, $cassetteName);
    }

    protected function createVCRLibraryHooksSoapHook(): SoapHook
    {
        return new LibraryHooks\SoapHook(
            $this->getOrCreate('VCR\CodeTransform\SoapCodeTransform'),
            $this->getOrCreate('VCR\Util\StreamProcessor')
        );
    }

    protected function createVCRLibraryHooksCurlHook(): CurlHook
    {
        return new LibraryHooks\CurlHook(
            $this->getOrCreate('VCR\CodeTransform\CurlCodeTransform'),
            $this->getOrCreate('VCR\Util\StreamProcessor')
        );
    }

    /**
     * Returns the same VCRFactory instance on ever call (singleton).
     *
     * @param Configuration $config (Optional) configuration
     *
     * @return VCRFactory
     */
    public static function getInstance(Configuration $config = null): self
    {
        if (!self::$instance) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Returns an instance for specified class name and parameters.
     *
     * @param string  $className class name to get a instance for
     * @param mixed[] $params    constructor arguments for this class
     *
     * @return mixed an instance for specified class name and parameters
     */
    public static function get(string $className, array $params = [])
    {
        return self::getInstance()->getOrCreate($className, $params);
    }

    /**
     * Returns an instance for specified classname and parameters.
     *
     * @param string  $className class name to get a instance for
     * @param mixed[] $params    constructor arguments for this class
     *
     * @return mixed
     */
    public function getOrCreate(string $className, array $params = [])
    {
        $key = $className.implode('-', $params);

        if (isset($this->mapping[$key])) {
            return $this->mapping[$key];
        }

        $callable = [$this, $this->getMethodName($className)];

        if (\is_callable($callable)) {
            $instance = \call_user_func_array($callable, $params);
        } else {
            $instance = new $className();
        }

        return $this->mapping[$key] = $instance;
    }

    /**
     * Example:.
     *
     *   ClassName: \Tux\Foo\Linus
     *   Returns: createTuxFooLinus
     */
    protected function getMethodName(string $className): string
    {
        return 'create'.str_replace('\\', '', $className);
    }
}
