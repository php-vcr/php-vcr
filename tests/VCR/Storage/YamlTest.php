<?php

namespace VCR\Storage;

use org\bovigo\vfs\vfsStream;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class YamlTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        vfsStream::setup('test');
        $this->filePath = vfsStream::url('test/') . DIRECTORY_SEPARATOR . 'yaml_test';
        $this->yamlObject = new Yaml(vfsStream::url('test/'), 'yaml_test');
    }

    public function testIterateOneObject()
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1: val1',
            array(
                array('para1' => 'val1'),
            ),
            'Single yaml object was not parsed correctly.'
        );
    }

    public function testIterateTwoObjects()
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1: val1' . "\n"
            . '-' . "\n"
            . '   para2: val2',
            array(
                array('para1' => 'val1'),
                array('para2' => 'val2'),
            ),
            'Two yaml objects were not parsed correctly.'
        );
    }

    public function testIterateFirstNestedObject()
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1:' . "\n"
            . '        para2: val2' . "\n"
            . '-' . "\n"
            . '    para3: val3',
            array(
                array('para1' => array('para2' => 'val2')),
                array('para3' => 'val3'),
            ),
            'Nested yaml objects were not parsed correctly.'
        );
    }

    public function testIterateSecondNestedObject()
    {
        $this->iterateAndTest(
            '-' . "\n"
            . '    para1: val1' . "\n"
            . '-' . "\n"
            . '    para2:' . "\n"
            . '        para3: val3' . "\n",
            array(
                array('para1' => 'val1'),
                array('para2' => array('para3' => 'val3')),
            ),
            'Nested yaml objects were not parsed correctly.'
        );
    }

    public function testIterateEmpty()
    {
        $this->iterateAndTest(
            '',
            array(),
            'Empty yaml was not parsed correctly.'
        );
    }

    public function testStoreRecording()
    {
        $expected = array(
            'request' => 'some request',
            'response' => 'some response'
        );

        $this->yamlObject->storeRecording($expected);

        $actual = array();
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }

        $this->assertEquals($expected, $actual[0], 'Storing and reading a recording failed.');
    }

    public function testStoreTwoRecording()
    {
        $expected = array(
            'request'  => array('headers' => array('Content-Type' => 'application/json')),
            'response' => array('body' => 'ok', 'status' => 200)
        );

        $this->yamlObject->storeRecording($expected);
        $this->yamlObject->storeRecording($expected);

        $actual = array();
        foreach ($this->yamlObject as $recording) {
            $actual[] = $recording;
        }
        $this->assertCount(2, $actual, 'More that two recordings stores.');
        $this->assertEquals($expected, $actual[0], 'Storing and reading first recording failed.');
        $this->assertEquals($expected, $actual[1], 'Storing and reading second recording failed.');
    }

    private function iterateAndTest($yaml, $expected, $message)
    {
        file_put_contents($this->filePath, $yaml);

        $actual = array();
        foreach ($this->yamlObject as $object) {
            $actual[] = $object;
        }

        $this->assertEquals($expected, $actual, $message);
    }
}
