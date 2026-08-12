<?php

declare(strict_types=1);

namespace VCR\Storage;

use VCR\Storage\Redaction\RedactionRules;
use VCR\Storage\Redaction\Scope;

/**
 * Wraps another storage factory so every cassette it creates has sensitive fields redacted.
 */
class RedactingStorageFactory implements StorageFactoryInterface
{
    /**
     * Standard sensitive response headers stripped by {@see self::withDefaults()}.
     *
     * @var list<string>
     */
    private const DEFAULT_SENSITIVE_RESPONSE_HEADERS = [
        'Set-Cookie',
        'WWW-Authenticate',
        'Proxy-Authenticate',
    ];

    private StorageFactoryInterface $storageFactory;

    private RedactionRules $rules;

    public function __construct(StorageFactoryInterface $storageFactory, RedactionRules $rules)
    {
        $this->storageFactory = $storageFactory;
        $this->rules = $rules;
    }

    public static function withRules(StorageFactoryInterface $storageFactory, RedactionRules $rules): self
    {
        return new self($storageFactory, $rules);
    }

    /**
     * Builds a factory that strips the standard sensitive response headers with no configuration:
     * `Set-Cookie`, `WWW-Authenticate`, `Proxy-Authenticate`.
     */
    public static function withDefaults(StorageFactoryInterface $storageFactory): self
    {
        $rules = RedactionRules::create();

        foreach (self::DEFAULT_SENSITIVE_RESPONSE_HEADERS as $headerName) {
            $rules->header($headerName, null, Scope::RESPONSE);
        }

        return new self($storageFactory, $rules);
    }

    public function create(string $cassettePath, string $cassetteName): StorageInterface
    {
        $storage = $this->storageFactory->create($cassettePath, $cassetteName);

        if ($storage instanceof PurgeableStorageInterface) {
            return new PurgeableRedactingStorage($storage, $this->rules);
        }

        return new RedactingStorage($storage, $this->rules);
    }
}
