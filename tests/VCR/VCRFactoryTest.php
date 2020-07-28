<?php

namespace VCR;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * Test instance creation.
 */
class VCRFactoryTest extends TestCase
{
    /**
     * @dataProvider instanceProvider
     * @covers \VCR\VCRFactory::createVCRVideorecorder()
     */
    public function testCreateInstances($instance)
    {
        $this->assertInstanceOf($instance, VCRFactory::get($instance));
    }

    public function instanceProvider()
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
     */
    public function testCreateStorage($storage, $className)
    {
        vfsStream::setup('test');

        VCRFactory::get('VCR\Configuration')->setStorage($storage);
        VCRFactory::get('VCR\Configuration')->setCassettePath(vfsStream::url('test/'));

        $instance = VCRFactory::get('Storage', [rand()]);

        $this->assertInstanceOf($className, $instance);
    }

    public function storageProvider()
    {
        return [
            ['json', 'VCR\Storage\Json'],
            ['yaml', 'VCR\Storage\Yaml'],
        ];
    }
}
