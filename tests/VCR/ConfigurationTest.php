<?php

namespace VCR;

/**
 *
 */
class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Configuration
     */
    private $config;

    public function setUp()
    {
        $this->config = new Configuration;
    }

    public function testSetCassettePathThrowsErrorOnInvalidPath()
    {
        $this->setExpectedException(
            'VCR\VCRException',
            "Cassette path 'invalid_path' is not a directory. Please either "
            . "create it or set a different cassette path using "
            . "\\VCR\\VCR::configure()->setCassettePath('directory')."
        );
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

    public function testEnableLibraryHooksFailsWithWrongHookName()
    {
        $this->setExpectedException('InvalidArgumentException', "Library hooks don't exist: non_existing");
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

    public function testEnableRequestMatchersFailsWithNoExistingName()
    {
        $this->setExpectedException('InvalidArgumentException', "Request matchers don't exist: wrong, name");
        $this->config->enableRequestMatchers(array('wrong', 'name'));
    }

    public function testAddRequestMatcherFailsWithNoName()
    {
        $this->setExpectedException('VCR\VCRException', "A request matchers name must be at least one character long. Found ''");
        $expected = function ($first, $second) {
            return true;
        };
        $this->config->addRequestMatcher('', $expected);
    }

    public function testAddRequestMatcherFailsWithWrongCallback()
    {
        $this->setExpectedException('VCR\VCRException', "Request matcher 'example' is not callable.");
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

    public function testSetStorageInvalidName()
    {
        $this->setExpectedException('VCR\VCRException', "Storage 'Does not exist' not available.");
        $this->config->setStorage('Does not exist');
    }

    public function testGetStorage()
    {
        $class = $this->config->getStorage();
        $this->assertTrue(in_array('Iterator', class_implements($class)));
        $this->assertTrue(in_array('Traversable', class_implements($class)));
        $this->assertTrue(in_array('VCR\Storage\AbstractStorage', class_parents($class)));
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

    public function testSetModeInvalidName()
    {
        $this->setExpectedException('VCR\VCRException', "Mode 'invalid' does not exist.");
        $this->config->setMode('invalid');
    }
}
