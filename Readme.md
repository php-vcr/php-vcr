# PHP-VCR

This is a port of [VCR](http://github.com/vcr/vcr) for ruby.

Record your test suite's HTTP interactions and replay them during future test runs for fast, deterministic, accurate tests.

## Features

* Automatically records and replays your HTTP(s) interactions with minimal setup/configuration code.
* Supports common http functions and extensions
  * everyting using [streamWrapper](http://php.net/manual/en/class.streamwrapper.php): fopen(), fread(), file_get_contents(), ... without any modification
  * [SoapClient](http://www.php.net/manual/en/soapclient.soapclient.php) using your own wrapper class
  * curl(),  using [runkit extension](http://www.php.net/manual/en/book.runkit.php) and `runkit.internal_override=1` in your php.ini
  * Todo: curl_multi()
* The same request can receive different responses in different tests--just use different cassettes.
* Disables all HTTP requests that you don't explicitly allow (except SoapClient if not configured).
* Request matching is configurable based on HTTP method, URI, host, path, body and headers, or you can easily
  implement a custom request matcher to handle any need.
* Todo: The recorded requests and responses are stored on disk in a serialization format of your choice
  (currently YAML and JSON are built in, and you can easily implement your own custom serializer)
  and can easily be inspected and edited.
* Todo: Supports PHPUnit annotations
* Todo: Automatically re-records cassettes on a configurable regular interval to keep them fresh and current.

## Usage example

Using annotations:

``` php
class VCRTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // Initialize VCR
        $this->vcr = new Adri\VCR;
    }

    public function testNoCassetteUsed()
    {
        // Now all HTTP requests will be intercepted, an exception is thrown
        // if you don't provide a @VCR:useCassette($name) annotation, example:
        $this->setExpectedException('\BadMethodCallException');
        file_get_contents('http://example.com');
    }

    /**
     * You can use a test method annotation...
     * @VCR:useCassette('example')
     */
    public function testUsingAnnotation()
    {
        // Following request will be recorded once and replayed in furture test runs
        $result = file_get_contents('http://example.com');
        $this->assertNotEmpty($result);
    }
}
```

Using inline method calls:

``` php
class VCRTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // Initialize VCR
        $this->vcr = new Adri\VCR;
    }

    public function testUsingInlineMethodCall()
    {
        // .... or use an inline method call
        $this->vcr->useCassette('example');

        // Following request will be recorded once and replayed in furture test runs
        $result = file_get_contents('http://example.com');
        $this->assertNotEmpty($result);

    }

    public function tearDown()
    {
        // When using inline method calls, make sure to clean up after every test
        // This is not needed when using annotations
        $this->vcr->ejectCassette();
    }

}
```


## Installation

There is no release yet, sorry.

``` bash
git clone git@github.com:adri/php-vc
cd php-vcr
composer install -dev
phpunit tests
```

## Dependencies

PHP-VCR depends on:

  * PHP 5.3+
  * Curl extension
  * HTTP library [Guzzle](http://guzzlephp.org)
  * [symfony/yaml](https://github.com/symfony/yaml)
  * [beberlei/assert](https://github.com/beberlei/assert)
  * (optional) runkit extension with `runkit.internal_override=1` in php.ini if you want to intercept curl

Composer installs all depenencies except extensions like curl or runkit.

## Run tests

``` php
composer update -dev
phpunit ./tests
```

## Changelog

 * 2013-02-22 Added YAML support
 * 2013-02-21 Added custom request matcher
 * 2013-02-21 Added JSON storage which uses less memory
 * 2013-02-21 Added support for binary data
 * 2013-02-20 Added Soap support
 * 2013-02-19 Curl hook fixes, more tests
 * 2013-02-18 First prototype

## Copyright
Copyright (c) 2013 Adrian Philipp. Released under the terms of the MIT license. See LICENSE for details.

<!--
name of the projects and all sub-modules and libraries (sometimes they are named different and very confusing to new users)
descriptions of all the project, and all sub-modules and libraries
5-line code snippet on how its used (if it's a library)
copyright and licensing information (or "Read LICENSE")
instruction to grab the documentation
instructions to install, configure, and to run the programs
instruction to grab the latest code and detailed instructions to build it (or quick overview and "Read INSTALL")
list of authors or "Read AUTHORS"
instructions to submit bugs, feature requests, submit patches, join mailing list, get announcements, or join the user or dev community in other forms
other contact info (email address, website, company name, address, etc)
a brief history if it's a replacement or a fork of something else
legal notices (crypto stuff)
-->
