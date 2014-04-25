<?php

namespace VCR;

/**
 * Test integration of PHPVCR with PHPUnit.
 */
class RequestTest extends \PHPUnit_Framework_TestCase
{
    protected $request;

    public function setUp()
    {
        $this->request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));
    }

    public function testGetHeaders()
    {
        $this->assertEquals(
            array(
                'User-Agent' => 'Unit-Test',
                'Host'       => 'example.com'
            ),
            $this->request->getHeaders()
        );
    }

    public function testSetMethod()
    {
        $this->request->setMethod('post');

        $this->assertEquals('POST', $this->request->getMethod());
    }

    public function testSendFailsMissingClient()
    {
        $this->setExpectedException('Guzzle\Common\Exception\RuntimeException', 'A client must be set on the request');
        $this->request->send();
    }

    public function testMatches()
    {
        $request = new Request('GET', 'http://example.com', array('User-Agent' => 'Unit-Test'));

        $this->assertTrue($this->request->matches($request, array(array('VCR\RequestMatcher', 'matchMethod'))));
    }

    public function testDoesntMatch()
    {
        $request = new Request('POST', 'http://example.com', array('User-Agent' => 'Unit-Test'));

        $this->assertFalse($this->request->matches($request, array(array('VCR\RequestMatcher', 'matchMethod'))));
    }

    public function testMatchesThrowsExceptionIfMatcherNotFound()
    {
        $request = new Request('POST', 'http://example.com', array('User-Agent' => 'Unit-Test'));
        $this->setExpectedException(
            '\BadFunctionCallException',
            "Matcher could not be executed. Array\n(\n    [0] => some\n    [1] => method\n)\n"
        );
        $this->request->matches($request, array(array('some', 'method')));
    }

    public function testGetHeadersAsObject()
    {
        $this->assertEquals(
            array(
                'User-Agent' => array('Unit-Test'),
                'Host'       => array('example.com')
            ),
            $this->request->getHeaders(true)->toArray()
        );
    }

    public function testRestoreRequest()
    {
        $restoredRequest = Request::fromArray($this->request->toArray());
        $this->assertEquals(
            array(
                'method'      => 'GET',
                'url'         => 'http://example.com',
                'headers'     => array(
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                 )
            ),
            $restoredRequest->toArray()
        );
    }

    public function testStorePostFields()
    {
        $this->request->addPostFields(array('para1' => 'val1'));
        $this->assertEquals(
            array(
                'method'      => 'GET',
                'url'         => 'http://example.com',
                'headers'     => array(
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8'
                    ),
                'post_fields' => array('para1' => 'val1'),
            ),
            $this->request->toArray()
        );
    }

    public function testRestorePostFields()
    {
        $this->request->addPostFields(array('para1' => 'val1'));
        $restoredRequest = Request::fromArray($this->request->toArray());
        $this->assertEquals(
            array(
                'method'      => 'GET',
                'url'         => 'http://example.com',
                'headers'     => array(
                    'User-Agent' => 'Unit-Test',
                    'Host' => 'example.com',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8'
                    ),
                'post_fields' => array('para1' => 'val1'),
            ),
            $restoredRequest->toArray()
        );
    }

    public function testStorePostFile()
    {
        $this->request->addPostFile('field_name', 'tests/fixtures/unittest_curl_test');
        $this->assertEquals(
            array(
                'method'      => 'GET',
                'url'         => 'http://example.com',
                'headers'     => array(
                    'User-Agent'   => 'Unit-Test',
                    'Host'         => 'example.com',
                    'Content-Type' => 'multipart/form-data',
                    'Expect'       => '100-Continue'
                ),
                'post_files' => array(
                    array(
                        'fieldName'   => 'field_name',
                        'contentType' => 'application/octet-stream',
                        'filename'    => 'tests/fixtures/unittest_curl_test',
                        'postname'    => 'unittest_curl_test',
                    )
                ),
            ),
            $this->request->toArray()
        );
    }

    public function testRestorePostFiles()
    {
        $this->request->addPostFile('field_name', 'tests/fixtures/unittest_curl_test');
        $restoredRequest = Request::fromArray($this->request->toArray());
        $this->assertEquals(
            array(
                'method'      => 'GET',
                'url'         => 'http://example.com',
                'headers'     => array(
                    'User-Agent'   => 'Unit-Test',
                    'Host'         => 'example.com',
                    'Content-Type' => 'multipart/form-data',
                    'Expect'       => '100-Continue'
                    ),
                'post_files' => array(
                    array(
                        'fieldName'   => 'field_name',
                        'contentType' => 'application/octet-stream',
                        'filename'    => 'tests/fixtures/unittest_curl_test',
                        'postname'    => 'unittest_curl_test',
                    )
                ),
            ),
            $restoredRequest->toArray()
        );
    }
}
