<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\VCRFactory;

final class VCRFactoryTest extends TestCase
{
    /**
     * @dataProvider instanceProvider
     *
     * @covers \VCR\VCRFactory::createVCRVideorecorder()
     *
     * @param class-string $instance
     */
    public function testCreateInstances(string $instance): void
    {
        $this->assertInstanceOf($instance, VCRFactory::get($instance));
    }

    /** @return array<class-string[]> */
    public function instanceProvider(): array
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
    public function storageProvider()
    {
        return [
            ['json', 'VCR\Storage\Json'],
            ['yaml', 'VCR\Storage\Yaml'],
        ];
    }
}
