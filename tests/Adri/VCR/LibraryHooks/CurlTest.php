<?php

namespace Adri\PHPVCR\LibraryHooks;

use Adri\PHPVCR\Response;

/**
 * Test if intercepting http/https using stream wrapper works.
 */
class CurlTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Adri\PHPVCR\LibraryHooks\Curl
     */
    private $curl;

    public $expected = 'example response';

    public function setUp()
    {
        $testClass = $this;
        $this->curl = new Curl(function($request) use($testClass) {
            return new Response(200, null, $testClass->expected);
        });
    }

    public function testEnable()
    {
        $this->curl->enable();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://google.com/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $actual = curl_exec($ch);
        curl_close($ch);

        $this->assertEquals($this->expected, $actual);
    }

    public function testDisable()
    {
        $this->curl->disable();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://google.com/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $actual = curl_exec($ch);
        curl_close($ch);

        $this->assertNotEquals($this->expected, $actual);
    }

    public function testDisableTwice()
    {
        $this->curl->disable();
        $this->curl->disable();
    }

    public function testEnableTwice()
    {
        $this->curl->enable();
        $this->curl->enable();
    }

    public function tearDown()
    {
    }
}
