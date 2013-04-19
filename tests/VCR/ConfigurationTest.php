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

    public function testEnableRequestMatchers()
    {
        $actual = $this->config->enableRequestMatchers(array('body', 'headers'));
        $this->assertEquals(
            array(
                array('\VCR\RequestMatcher', 'matchHeaders'),
                array('\VCR\RequestMatcher', 'matchBody'),
            ),
            $this->config->getRequestMatchers()
        );
    }

    public function testAddRequestMatcherFailsWithNoName()
    {
        $this->setExpectedException('\VCR\VCRException');
        $expected = function($first, $second) {
            return true;
        };
        $actual = $this->config->addRequestMatcher('', $expected);
    }


    public function testAddRequestMatcherFailsWithWrongCallback()
    {
        $this->setExpectedException('\VCR\VCRException', "Request matcher 'example' is not callable.");
        $actual = $this->config->addRequestMatcher('example', array());
    }

    public function testAddRequestMatchers()
    {
        $expected = function($first, $second) {
            return true;
        };
        $actual = $this->config->addRequestMatcher('new_matcher', $expected);
        $this->assertContains($expected, $this->config->getRequestMatchers());
    }

    public function testSetStorageInvalidName()
    {
        $this->setExpectedException('\VCR\VCRException');
        $this->config->setStorage('Does not exist');
    }

    public function testGetStorage()
    {
        $class = $this->config->getStorage();
        $this->assertTrue(in_array("VCR\Storage\StorageInterface", class_implements($class)) || in_array("\VCR\Storage\StorageInterface", class_implements($class)));
    }

}
