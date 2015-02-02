<?php

namespace VCR\Storage;

use org\bovigo\vfs\vfsStream;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class JsonTest extends \PHPUnit_Framework_TestCase
{
    protected $handle;
    protected $filePath;
    protected $jsonObject;

    public function setUp()
    {
        vfsStream::setup('test');
        $this->filePath = vfsStream::url('test/') . 'json_test';
        $this->jsonObject = new Json(vfsStream::url('test/'), 'json_test');
    }

    public function testIterateOneObject()
    {
        $this->iterateAndTest(
            '[{"para1": "val1"}]',
            array(
                array('para1' => 'val1'),
            ),
            'Single json object was not parsed correctly.'
        );
    }

    public function testIterateTwoObjects()
    {
        $this->iterateAndTest(
            '[{"para1": "val1"}, {"para2": "val2"}]',
            array(
                array('para1' => 'val1'),
                array('para2' => 'val2'),
            ),
            'Two json objects were not parsed correctly.'
        );
    }

    public function testIterateFirstNestedObject()
    {
        $this->iterateAndTest(
            '[{"para1": {"para2": "val2"}}, {"para3": "val3"}]',
            array(
                array('para1' => array('para2' => 'val2')),
                array('para3' => 'val3'),
            ),
            'Nested json objects were not parsed correctly.'
        );
    }

    public function testIterateSecondNestedObject()
    {
        $this->iterateAndTest(
            '[{"para1": "val1"}, {"para2": {"para3": "val3"}}]',
            array(
                array('para1' => 'val1'),
                array('para2' => array('para3' => 'val3')),
            ),
            'Nested json objects were not parsed correctly.'
        );
    }

    public function testIterateEmpty()
    {
        $this->iterateAndTest(
            '[]',
            array(),
            'Empty json was not parsed correctly.'
        );
    }

    public function testStoreRecording()
    {
        $expected = array(
            'request' => 'some request',
            'response' => 'some response'
        );

        $this->jsonObject->storeRecording($expected);

        $actual = array();
        foreach ($this->jsonObject as $recording) {
            $actual[] = $recording;
        }

        $this->assertEquals($expected, $actual[0], 'Storing and reading a recording failed.');
    }

    public function testValidJson()
    {
        $stored = array(
            'request' => 'some request',
            'response' => 'some response'
        );
        $this->jsonObject->storeRecording($stored);
        $this->jsonObject->storeRecording($stored);

        $this->assertJson(file_get_contents($this->filePath));

    }

    public function testStoreRecordingWhenBlankFileAlreadyExists()
    {
        vfsStream::create(array('blank_file_test' => ''));
        $filePath = vfsStream::url('test/') . 'blank_file_test';

        $jsonObject = new Json(vfsStream::url('test/'), 'blank_file_test');
        $stored = array(
            'request' => 'some request',
            'response' => 'some response'
        );
        $jsonObject->storeRecording($stored);

        $this->assertJson(file_get_contents($filePath));
    }

    private function iterateAndTest($json, $expected, $message)
    {
        file_put_contents($this->filePath, $json);

        $actual = array();
        foreach ($this->jsonObject as $object) {
            $actual[] = $object;
        }

        $this->assertEquals($expected, $actual, $message);
    }
}
