<?php

declare(strict_types=1);

namespace VCR;

use Assert\Assertion;
use VCR\LibraryHooks\CurlHook;
use VCR\LibraryHooks\SoapHook;
use VCR\Storage\Storage;
use VCR\Util\StreamProcessor;

class VCRFactory
{
    protected Configuration $config;

    /**
     * @var array<string, object>
     */
    protected array $mapping = [];

    protected static ?self $instance = null;

    protected function __construct(?Configuration $config = null)
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

    protected function createVCRUtilStreamProcessor(): StreamProcessor
    {
        return new StreamProcessor($this->config);
    }

    /** @return Storage<array> */
    protected function createStorage(string $cassetteName): Storage
    {
        $dsn = $this->config->getCassettePath();
        $className = $this->config->getStorage();
        Assertion::subclassOf(
            $className,
            Storage::class,
            \sprintf('Storage class "%s" is not a subclass of "%s".', $className, Storage::class)
        );

        /** @var Storage $storage */
        $storage = new $className($dsn, $cassetteName);

        return $storage;
    }

    protected function createVCRLibraryHooksSoapHook(): SoapHook
    {
        return new SoapHook(
            $this->getOrCreate('VCR\CodeTransform\SoapCodeTransform'),
            $this->getOrCreate('VCR\Util\StreamProcessor')
        );
    }

    protected function createVCRLibraryHooksCurlHook(): CurlHook
    {
        return new CurlHook(
            $this->getOrCreate('VCR\CodeTransform\CurlCodeTransform'),
            $this->getOrCreate('VCR\Util\StreamProcessor')
        );
    }

    public static function getInstance(?Configuration $config = null): self
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
    public static function get(string $className, array $params = []): mixed
    {
        return self::getInstance()->getOrCreate($className, $params);
    }

    /**
     * Returns an instance for specified classname and parameters.
     *
     * @param string  $className class name to get a instance for
     * @param mixed[] $params    constructor arguments for this class
     */
    public function getOrCreate(string $className, array $params = []): mixed
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
