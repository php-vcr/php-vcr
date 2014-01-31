<?php

namespace VCR;

/**
 *
 */
class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->config = new Configuration;
    }

    public function testGetLibraryHooks()
    {
        $this->assertEquals(
            array(
                'VCR\LibraryHooks\StreamWrapper',
                'VCR\LibraryHooks\Curl',
                'VCR\LibraryHooks\Soap',
            ),
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableLibraryHooks()
    {
        $this->config->enableLibraryHooks(array('stream_wrapper'));
        $this->assertEquals(
            array(
                'VCR\LibraryHooks\StreamWrapper',
            ),
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableSingleLibraryHook()
    {
        $this->config->enableLibraryHooks('stream_wrapper');
        $this->assertEquals(
            array(
                'VCR\LibraryHooks\StreamWrapper',
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
        $actual = $this->config->enableRequestMatchers(array('body', 'headers'));
        $this->assertEquals(
            array(
                array('VCR\RequestMatcher', 'matchHeaders'),
                array('VCR\RequestMatcher', 'matchBody'),
            ),
            $this->config->getRequestMatchers()
        );
    }

    public function testAddRequestMatcherFailsWithNoName()
    {
        $this->setExpectedException('VCR\VCRException', "A request matchers name must be at least one character long. Found ''");
        $expected = function ($first, $second) {
            return true;
        };
        $actual = $this->config->addRequestMatcher('', $expected);
    }

    public function testAddRequestMatcherFailsWithWrongCallback()
    {
        $this->setExpectedException('VCR\VCRException', "Request matcher 'example' is not callable.");
        $actual = $this->config->addRequestMatcher('example', array());
    }

    public function testAddRequestMatchers()
    {
        $expected = function ($first, $second) {
            return true;
        };
        $actual = $this->config->addRequestMatcher('new_matcher', $expected);
        $this->assertContains($expected, $this->config->getRequestMatchers());
    }

    public function testSetStorageInvalidName()
    {
        $this->setExpectedException('VCR\VCRException', "Storage 'Does not exist' not available.");
        $this->config->setStorage('Does not exist');
    }

    public function testGetStorage()
    {
        $class = $this->config->getStorage();
        $this->assertTrue(in_array("VCR\Storage\Storage", class_implements($class)));
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
}
