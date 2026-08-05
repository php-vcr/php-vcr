<?php

declare(strict_types=1);

namespace VCR;

use VCR\Storage\Blackhole;
use VCR\Storage\BlackholeStorageFactory;
use VCR\Storage\Json;
use VCR\Storage\JsonStorageFactory;
use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\StorageInterface;
use VCR\Storage\Yaml;
use VCR\Storage\YamlStorageFactory;
use VCR\Util\Assertion;

/**
 * Configuration stores a Videorecorders configuration options.
 *
 * Those configuration options might be:
 *  - which LibraryHook to use,
 *  - where to store cassettes or
 *  - which files to scan when filtering source code.
 */
class Configuration
{
    /**
     * Storage names accepted by the deprecated setStorage().
     *
     * @var array<string, class-string<StorageFactoryInterface>>
     */
    private const DEPRECATED_STORAGE_FACTORIES = [
        'blackhole' => BlackholeStorageFactory::class,
        'json' => JsonStorageFactory::class,
        'yaml' => YamlStorageFactory::class,
    ];

    /**
     * Storage class the deprecated getStorage() reports per built-in factory.
     *
     * @var array<class-string<StorageFactoryInterface>, class-string<StorageInterface>>
     */
    private const DEPRECATED_STORAGE_CLASSES = [
        BlackholeStorageFactory::class => Blackhole::class,
        JsonStorageFactory::class => Json::class,
        YamlStorageFactory::class => Yaml::class,
    ];

    private string $cassettePath = 'tests/fixtures';

    /**
     * List of enabled LibraryHook names.
     *
     * A value of null means all hooks are enabled.
     *
     * @see LibraryHooks\LibraryHook
     *
     * @var string[]|null list of enabled LibraryHook names
     */
    private $enabledLibraryHooks;

    /**
     * List of library hooks.
     *
     * Format:
     * array(
     *  'name' => 'class name'
     * )
     *
     * @var array<string, class-string> List of library hooks
     */
    private $availableLibraryHooks = [
        'stream_wrapper' => 'VCR\LibraryHooks\StreamWrapperHook',
        'curl' => 'VCR\LibraryHooks\CurlHook',
        'soap' => 'VCR\LibraryHooks\SoapHook',
    ];

    /**
     * Factory creating the Storage for an inserted cassette.
     *
     * Only one factory is active at a time. Created lazily in
     * getStorageFactory() so the default stays a plain YAML storage.
     */
    private ?StorageFactoryInterface $storageFactory = null;

    /**
     * A value of null means all RequestMatchers are enabled.
     *
     * @var string[] names of the RequestMatchers which are enabled
     */
    private $enabledRequestMatchers;

    /**
     * Format:
     * array(
     *  'name' => callback
     * ).
     *
     * The RequestMatcher callback takes two Request objects and
     * returns true if they match or false otherwise.
     *
     * @var array<string,callable(Request, Request):bool> List of RequestMatcher names and callbacks
     */
    private $availableRequestMatchers = [
        'method' => [RequestMatcher::class, 'matchMethod'],
        'url' => [RequestMatcher::class, 'matchUrl'],
        'host' => [RequestMatcher::class, 'matchHost'],
        'headers' => [RequestMatcher::class, 'matchHeaders'],
        'body' => [RequestMatcher::class, 'matchBody'],
        'post_fields' => [RequestMatcher::class, 'matchPostFields'],
        'query_string' => [RequestMatcher::class, 'matchQueryString'],
        'soap_operation' => [RequestMatcher::class, 'matchSoapOperation'],
    ];

    /**
     * A whitelist is a list of paths.
     *
     * When processing files for code transformation, only files matching
     * those paths are considered. By default the whitelist is empty and
     * all files (which are not blacklisted) are being considered for
     * code transformation.
     *
     * @var string[] a whitelist is a list of paths
     */
    private $whiteList = [];

    /**
     * A blacklist is a list of paths which may not be processed for code transformation.
     *
     * Files in this path are left as is. Blacklisting PHP-VCRs own paths is necessary
     * to avoid infinite loops.
     *
     * @var string[] a blacklist is a list of paths
     */
    private $blackList = ['src/VCR/LibraryHooks/', 'src/VCR/Util/SoapClient', 'src/VCR/Util/StreamProcessor', 'tests/VCR/Filter'];

    private string $mode = VCR::MODE_NEW_EPISODES;

    private bool $recordIdenticalRequests = true;

    /**
     * List of available modes.
     *
     * Format:
     * array(
     *  'name'
     * )
     *
     * @var string[] list of available modes
     */
    private $availableModes = [
        VCR::MODE_NEW_EPISODES,
        VCR::MODE_ONCE,
        VCR::MODE_NONE,
        VCR::MODE_ALL,
    ];

    /**
     * @return string[]
     */
    public function getBlackList(): array
    {
        return $this->blackList;
    }

    /**
     * @param string|string[] $paths
     */
    public function setBlackList($paths): self
    {
        $paths = \is_array($paths) ? $paths : [$paths];

        $this->blackList = $paths;

        return $this;
    }

    public function getCassettePath(): string
    {
        $this->assertValidCassettePath($this->cassettePath);

        return $this->cassettePath;
    }

    /**
     * @throws VCRException if provided cassette path is invalid
     */
    public function setCassettePath(string $cassettePath): self
    {
        $this->assertValidCassettePath($cassettePath);
        $this->cassettePath = $cassettePath;

        return $this;
    }

    /**
     * Returns a list of enabled LibraryHook class names.
     *
     * Only class names are returned, any object creation happens
     * in the VCRFactory.
     *
     * @return string[] list of LibraryHook class names
     */
    public function getLibraryHooks(): array
    {
        if (null === $this->enabledLibraryHooks) {
            return array_values($this->availableLibraryHooks);
        }

        return array_values(array_intersect_key(
            $this->availableLibraryHooks,
            array_flip($this->enabledLibraryHooks)
        ));
    }

    /**
     * Enables specified LibraryHook(s) by its name.
     *
     * @param string|string[] $hooks name of the LibraryHook(s) to enable
     *
     * @throws \InvalidArgumentException if a specified library hook doesn't exist
     */
    public function enableLibraryHooks($hooks): self
    {
        $hooks = \is_array($hooks) ? $hooks : [$hooks];
        $invalidHooks = array_diff($hooks, array_keys($this->availableLibraryHooks));
        if ($invalidHooks) {
            throw new \InvalidArgumentException("Library hooks don't exist: ".implode(', ', $invalidHooks));
        }
        $this->enabledLibraryHooks = $hooks;

        return $this;
    }

    /**
     * Returns the class name of the storage to use.
     *
     * @return string class name of the storage to use
     *
     * @throws VCRException if a custom storage factory is configured, whose
     *                      storage class cannot be resolved up front
     *
     * @deprecated since 1.12, use {@see self::getStorageFactory()}. Removed in
     *             the next major.
     */
    public function getStorage(): string
    {
        $factoryClass = \get_class($this->getStorageFactory());

        if (!isset(self::DEPRECATED_STORAGE_CLASSES[$factoryClass])) {
            throw new VCRException(\sprintf('Cannot resolve a storage class name for storage factory "%s". Please use getStorageFactory() instead.', $factoryClass), 0);
        }

        return self::DEPRECATED_STORAGE_CLASSES[$factoryClass];
    }

    /**
     * Returns the StorageFactory used to create a Storage per cassette.
     *
     * Defaults to a YAML storage when no factory was configured.
     */
    public function getStorageFactory(): StorageFactoryInterface
    {
        if (null === $this->storageFactory) {
            $this->storageFactory = new YamlStorageFactory();
        }

        return $this->storageFactory;
    }

    /**
     * Sets the StorageFactory used to create a Storage per cassette.
     *
     * Implement StorageFactoryInterface to plug in a custom storage backend
     * together with its own dependencies.
     */
    public function setStorageFactory(StorageFactoryInterface $storageFactory): self
    {
        $this->storageFactory = $storageFactory;

        return $this;
    }

    /**
     * Returns a list of enabled RequestMatcher callbacks.
     *
     * @return callable[] list of enabled RequestMatcher callbacks
     */
    public function getRequestMatchers(): array
    {
        if (null === $this->enabledRequestMatchers) {
            return array_values($this->availableRequestMatchers);
        }

        return array_values(array_intersect_key(
            $this->availableRequestMatchers,
            array_flip($this->enabledRequestMatchers)
        ));
    }

    /**
     * Adds a new RequestMatcher callback.
     *
     * @param string   $name     name of the RequestMatcher
     * @param callable $callback a callback taking two Request objects as parameters and returns true if those match
     *
     * @throws VCRException if specified parameters are invalid
     */
    public function addRequestMatcher(string $name, callable $callback): self
    {
        Assertion::minLength($name, 1, "A request matchers name must be at least one character long. Found ''");
        $this->availableRequestMatchers[$name] = $callback;

        return $this;
    }

    /**
     * Enables specified RequestMatchers by its name.
     *
     * @param string[] $matchers list of RequestMatcher names to enable
     *
     * @throws \InvalidArgumentException if a specified request matcher does not exist
     */
    public function enableRequestMatchers(array $matchers): self
    {
        $invalidMatchers = array_diff($matchers, array_keys($this->availableRequestMatchers));
        if ($invalidMatchers) {
            throw new \InvalidArgumentException("Request matchers don't exist: ".implode(', ', $invalidMatchers));
        }
        $this->enabledRequestMatchers = $matchers;

        return $this;
    }

    /**
     * @throws VCRException if a invalid storage name is given
     *
     * @deprecated since 1.12, use {@see self::setStorageFactory()}. Removed in
     *             the next major.
     */
    public function setStorage(string $storageName): self
    {
        Assertion::keyExists(self::DEPRECATED_STORAGE_FACTORIES, $storageName, "Storage '{$storageName}' not available.");

        $factoryClass = self::DEPRECATED_STORAGE_FACTORIES[$storageName];

        return $this->setStorageFactory(new $factoryClass());
    }

    /**
     * @return string[]
     */
    public function getWhiteList(): array
    {
        return $this->whiteList;
    }

    /**
     * @param string|string[] $paths single path or list of path which are whitelisted
     */
    public function setWhiteList($paths): self
    {
        $paths = \is_array($paths) ? $paths : [$paths];

        $this->whiteList = $paths;

        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        Assertion::choice($mode, $this->availableModes, "Mode '{$mode}' does not exist.");
        $this->mode = $mode;

        return $this;
    }

    public function getRecordIdenticalRequests(): bool
    {
        return $this->recordIdenticalRequests;
    }

    public function setRecordIdenticalRequests(bool $recordIdenticalRequests): self
    {
        $this->recordIdenticalRequests = $recordIdenticalRequests;

        return $this;
    }

    /**
     * @throws VCRException if cassette path is invalid
     */
    private function assertValidCassettePath(string $cassettePath): void
    {
        Assertion::directory(
            $cassettePath,
            "Cassette path '{$cassettePath}' is not a directory. Please either "
            .'create it or set a different cassette path using '
            ."\\VCR\\VCR::configure()->setCassettePath('directory')."
        );
    }
}
