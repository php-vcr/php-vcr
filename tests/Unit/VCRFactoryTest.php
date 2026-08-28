<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\StorageInterface;
use VCR\VCRFactory;

final class VCRFactoryTest extends TestCase
{
    /**
     * @dataProvider instanceProvider
     *
     * @covers \VCR\VCRFactory::createVCRVideorecorder
     *
     * @param class-string $instance
     */
    public function testCreateInstances(string $instance): void
    {
        $this->assertInstanceOf($instance, VCRFactory::get($instance));
    }

    /** @return array<class-string[]> */
    public static function instanceProvider(): array
    {
        return [
            ['VCR\Videorecorder'],
            ['VCR\Configuration'],
            ['VCR\Util\StreamProcessor'],
            ['VCR\Util\HttpClient'],
            ['VCR\CodeTransform\CurlCodeTransform'],
            ['VCR\CodeTransform\SoapCodeTransform'],
            ['VCR\LibraryHooks\CurlHook'],
            ['VCR\LibraryHooks\SoapHook'],
            ['VCR\LibraryHooks\StreamWrapperHook'],
        ];
    }

    /**
     * @dataProvider storageProvider
     *
     * @param class-string $className
     */
    public function testCreateStorage(string $storage, string $className): void
    {
        vfsStream::setup('test');

        VCRFactory::get('VCR\Configuration')->setStorage($storage);
        VCRFactory::get('VCR\Configuration')->setCassettePath(vfsStream::url('test/'));

        $instance = VCRFactory::get('Storage', [(string) random_int(0, getrandmax())]);

        $this->assertInstanceOf($className, $instance);
    }

    /** @return array<string[]> */
    public static function storageProvider(): array
    {
        return [
            ['json', 'VCR\Storage\Json'],
            ['yaml', 'VCR\Storage\Yaml'],
        ];
    }

    public function testCreateStorageUsesTheConfiguredStorageFactory(): void
    {
        $expectedStorage = $this->createMock(StorageInterface::class);
        $storageFactory = new class($expectedStorage) implements StorageFactoryInterface {
            private StorageInterface $storage;

            public function __construct(StorageInterface $storage)
            {
                $this->storage = $storage;
            }

            public function create(string $cassettePath, string $cassetteName): StorageInterface
            {
                return $this->storage;
            }
        };

        $configuration = VCRFactory::get('VCR\Configuration');
        $configuration->setStorageFactory($storageFactory);

        try {
            $instance = VCRFactory::get('Storage', [(string) random_int(0, getrandmax())]);

            $this->assertSame($expectedStorage, $instance);
        } finally {
            $configuration->setStorage('yaml');
        }
    }
}
