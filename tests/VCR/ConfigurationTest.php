<?php

namespace VCR;

use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    /**
     * @var Configuration
     */
    private $config;

    public function setUp()
    {
        $this->config = new Configuration();
    }

    public function testSetCassettePathThrowsErrorOnInvalidPath()
    {
        $this->expectException(
            VCRException::class,
            "Cassette path 'invalid_path' is not a directory. Please either "
            .'create it or set a different cassette path using '
            ."\\VCR\\VCR::configure()->setCassettePath('directory')."
        );
        $this->config->setCassettePath('invalid_path');
    }

    public function testGetLibraryHooks()
    {
        $this->assertEquals(
            [
                'VCR\LibraryHooks\StreamWrapperHook',
                'VCR\LibraryHooks\CurlHook',
                'VCR\LibraryHooks\SoapHook',
            ],
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableLibraryHooks()
    {
        $this->config->enableLibraryHooks(['stream_wrapper']);
        $this->assertEquals(
            [
                'VCR\LibraryHooks\StreamWrapperHook',
            ],
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableSingleLibraryHook()
    {
        $this->config->enableLibraryHooks('stream_wrapper');
        $this->assertEquals(
            [
                'VCR\LibraryHooks\StreamWrapperHook',
            ],
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableLibraryHooksFailsWithWrongHookName()
    {
        $this->expectException('InvalidArgumentException', "Library hooks don't exist: non_existing");
        $this->config->enableLibraryHooks(['non_existing']);
    }

    public function testEnableRequestMatchers()
    {
        $this->config->enableRequestMatchers(['body', 'headers']);
        $this->assertEquals(
            [
                ['VCR\RequestMatcher', 'matchHeaders'],
                ['VCR\RequestMatcher', 'matchBody'],
            ],
            $this->config->getRequestMatchers()
        );
    }

    public function testEnableRequestMatchersFailsWithNoExistingName()
    {
        $this->expectException('InvalidArgumentException', "Request matchers don't exist: wrong, name");
        $this->config->enableRequestMatchers(['wrong', 'name']);
    }

    public function testAddRequestMatcherFailsWithNoName()
    {
        $this->expectException('VCR\VCRException', "A request matchers name must be at least one character long. Found ''");
        $expected = function ($first, $second) {
            return true;
        };
        $this->config->addRequestMatcher('', $expected);
    }

    public function testAddRequestMatchers()
    {
        $expected = function () {
            return true;
        };
        $this->config->addRequestMatcher('new_matcher', $expected);
        $this->assertContains($expected, $this->config->getRequestMatchers());
    }

    /**
     * @dataProvider availableStorageProvider
     */
    public function testSetStorage($name, $className)
    {
        $this->config->setStorage($name);
        $this->assertEquals($className, $this->config->getStorage(), "$name should be class $className.");
    }

    public function availableStorageProvider()
    {
        return [
            ['json', 'VCR\Storage\Json'],
            ['yaml', 'VCR\Storage\Yaml'],
        ];
    }

    public function testSetStorageInvalidName()
    {
        $this->expectException('VCR\VCRException', "Storage 'Does not exist' not available.");
        $this->config->setStorage('Does not exist');
    }

    public function testGetStorage()
    {
        $class = $this->config->getStorage();
        $this->assertContains('Iterator', class_implements($class));
        $this->assertContains('Traversable', class_implements($class));
        $this->assertContains('VCR\Storage\AbstractStorage', class_parents($class));
    }

    public function testWhitelist()
    {
        $expected = ['Tux', 'Gnu'];

        $this->config->setWhiteList($expected);

        $this->assertEquals($expected, $this->config->getWhiteList());
    }

    public function testBlacklist()
    {
        $expected = ['Tux', 'Gnu'];

        $this->config->setBlackList($expected);

        $this->assertEquals($expected, $this->config->getBlackList());
    }

    public function testSetModeInvalidName()
    {
        $this->expectException('VCR\VCRException', "Mode 'invalid' does not exist.");
        $this->config->setMode('invalid');
    }
}
