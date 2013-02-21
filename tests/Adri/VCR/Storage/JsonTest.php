<?php

namespace Adri\VCR\Storage;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class JsonObjectIteratorTest extends \PHPUnit_Framework_TestCase
{
    private $handle;

    public function setUp()
    {
        $this->handle = fopen('php://temp/json_object', 'rw');
        $this->jsonObject = new Json($this->handle);
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

    private function iterateAndTest($json, $expected, $message)
    {
        fwrite($this->handle, $json);

        $actual = array();
        foreach ($this->jsonObject as $object) {
            $actual[] = $object;
        }

        $this->assertEquals($expected, $actual, $message);
    }

    public function tearDown()
    {
        fclose($this->handle);
    }
}
