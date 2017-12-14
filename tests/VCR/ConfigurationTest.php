<?php

namespace VCR;

use PHPUnit\Framework\TestCase;

/**
 *
 */
class ConfigurationTest extends TestCase
{
    /**
     * @var Configuration
     */
    private $config;

    public function setUp()
    {
        $this->config = new Configuration;
    }

    /**
     * @expectedException VCR\VCRException
     * @expectedExceptionMessage Cassette path 'invalid_path' is not a directory. Please either create it or set a different cassette path using \VCR\VCR::configure()->setCassettePath('directory').
     */
    public function testSetCassettePathThrowsErrorOnInvalidPath()
    {
        $this->config->setCassettePath('invalid_path');
    }

    public function testGetLibraryHooks()
    {
        $this->assertEquals(
            array(
                'VCR\LibraryHooks\StreamWrapperHook',
                'VCR\LibraryHooks\CurlHook',
                'VCR\LibraryHooks\SoapHook',
            ),
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableLibraryHooks()
    {
        $this->config->enableLibraryHooks(array('stream_wrapper'));
        $this->assertEquals(
            array(
                'VCR\LibraryHooks\StreamWrapperHook',
            ),
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableSingleLibraryHook()
    {
        $this->config->enableLibraryHooks('stream_wrapper');
        $this->assertEquals(
            array(
                'VCR\LibraryHooks\StreamWrapperHook',
            ),
            $this->config->getLibraryHooks()
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Library hooks don't exist: non_existing
     */
    public function testEnableLibraryHooksFailsWithWrongHookName()
    {
        $this->config->enableLibraryHooks(array('non_existing'));
    }

    public function testEnableRequestMatchers()
    {
        $this->config->enableRequestMatchers(array('body', 'headers'));
        $this->assertEquals(
            array(
                array('VCR\RequestMatcher', 'matchHeaders'),
                array('VCR\RequestMatcher', 'matchBody'),
            ),
            $this->config->getRequestMatchers()
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Request matchers don't exist: wrong, name
     */
    public function testEnableRequestMatchersFailsWithNoExistingName()
    {
        $this->config->enableRequestMatchers(array('wrong', 'name'));
    }

    /**
     * @expectedException VCR\VCRException
     * @expectedExceptionMessage A request matchers name must be at least one character long. Found ''
     */
    public function testAddRequestMatcherFailsWithNoName()
    {
        $expected = function ($first, $second) {
            return true;
        };
        $this->config->addRequestMatcher('', $expected);
    }

    /**
     * @expectedException VCR\VCRException
     * @expectedExceptionMessage Request matcher 'example' is not callable.
     */
    public function testAddRequestMatcherFailsWithWrongCallback()
    {
        $this->config->addRequestMatcher('example', array());
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
        return array(
            array('json', 'VCR\Storage\Json'),
            array('yaml', 'VCR\Storage\Yaml'),
        );
    }

    /**
     * @expectedException VCR\VCRException
     * @expectedExceptionMessage Storage 'Does not exist' not available.
     */
    public function testSetStorageInvalidName()
    {
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
        $expected = array('Tux', 'Gnu');

        $this->config->setWhiteList($expected);

        $this->assertEquals($expected, $this->config->getWhiteList());
    }

    public function testBlacklist()
    {
        $expected = array('Tux', 'Gnu');

        $this->config->setBlackList($expected);

        $this->assertEquals($expected, $this->config->getBlackList());
    }

    /**
     * @expectedException VCR\VCRException
     * @expectedExceptionMessage Mode 'invalid' does not exist.
     */
    public function testSetModeInvalidName()
    {
        $this->config->setMode('invalid');
    }
}
