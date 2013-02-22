<?php

namespace Adri\VCR;

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
                array('\Adri\VCR\RequestMatcher', 'matchHeaders'),
                array('\Adri\VCR\RequestMatcher', 'matchBody'),
            ),
            $this->config->getRequestMatchers()
        );
    }

    public function testAddRequestMatcherFailsWithNoName()
    {
        $this->setExpectedException('Adri\VCR\VCRException');
        $expected = function($first, $second) {
            return true;
        };
        $actual = $this->config->addRequestMatcher('', $expected);
    }


    public function testAddRequestMatcherFailsWithWrongCallback()
    {
        $this->setExpectedException('Adri\VCR\VCRException', "Request matcher 'example' is not callable.");
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
        $this->setExpectedException('Adri\VCR\VCRException');
        $this->config->setStorage('Does not exist');
    }

}
