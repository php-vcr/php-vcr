![PHP-VCR](https://dl.dropbox.com/u/13186339/blog/php-vcr.png)

[![Build Status](https://travis-ci.org/php-vcr/php-vcr.svg?branch=master)](https://travis-ci.org/php-vcr/php-vcr)
[![Dependency Status](http://www.versioneye.com/user/projects/525a6160632bac1e35000001/badge.png)](http://www.versioneye.com/user/projects/525a6160632bac1e35000001)
[![Code Coverage](https://scrutinizer-ci.com/g/php-vcr/php-vcr/badges/coverage.png?s=15cf1644c8cf37a868e03cfba809a5e24c78f285)](https://scrutinizer-ci.com/g/php-vcr/php-vcr/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/php-vcr/php-vcr/badges/quality-score.png?s=4f638dbca5eb51fb9c87a1dd45c5df94687d85bd)](https://scrutinizer-ci.com/g/php-vcr/php-vcr/)

This is a port of [VCR](http://github.com/vcr/vcr) for ruby.

Record your test suite's HTTP interactions and replay them during future test runs for fast, deterministic, accurate tests. A bit of documentation can be found on the [php-vcr website](http://php-vcr.github.io).

Disclaimer: Doing this in PHP is not as easy as in programming languages which support monkey patching (I'm looking at you, Ruby) – this project is not yet fully tested, so please use at your own risk!

## Features

* Automatically records and replays your HTTP(s) interactions with minimal setup/configuration code.
* Supports common http functions and extensions
  * everyting using [streamWrapper](http://php.net/manual/en/class.streamwrapper.php): fopen(), fread(), file_get_contents(), ... without any modification (except `$http_response_header` see #96)
  * [SoapClient](http://www.php.net/manual/en/soapclient.soapclient.php) by adding `\VCR\VCR\turnOn();` in your `tests/bootstrap.php`
  * curl(), by adding `\VCR\VCR::turnOn();` in your `tests/bootstrap.php`
* The same request can receive different responses in different tests -- just use different cassettes.
* Disables all HTTP requests that you don't explicitly allow by [setting the record mode](http://php-vcr.github.io/documentation/configuration/)
* [Request matching](http://php-vcr.github.io/documentation/configuration/) is configurable based on HTTP method, URI, host, path, body and headers, or you can easily
  implement a custom request matcher to handle any need.
* The recorded requests and responses are stored on disk in a serialization format of your choice
  (currently YAML and JSON are built in, and you can easily implement your own custom serializer)
* Supports PHPUnit annotations.

## Usage example

Using static method calls:

``` php
class VCRTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldInterceptStreamWrapper()
    {
        // After turning on the VCR will intercept all requests
        \VCR\VCR::turnOn();

        // Record requests and responses in cassette file 'example'
        \VCR\VCR::insertCassette('example');

        // Following request will be recorded once and replayed in future test runs
        $result = file_get_contents('http://example.com');
        $this->assertNotEmpty($result);

        // To stop recording requests, eject the cassette
        \VCR\VCR::eject();

        // Turn off VCR to stop intercepting requests
        \VCR\VCR::turnOff();
    }

    public function testShouldThrowExceptionIfNoCasettePresent()
    {
        $this->setExpectedException(
            'BadMethodCallException',
            "Invalid http request. No cassette inserted. Please make sure to insert "
            . "a cassette in your unit test using VCR::insertCassette('name');"
        );
        \VCR\VCR::turnOn();
        // If there is no cassette inserted, a request throws an exception
        file_get_contents('http://example.com');
    }
}
```

You can use annotations in PHPUnit by using [phpunit-testlistener-vcr](https://github.com/php-vcr/phpunit-testlistener-vcr):
``` php
class VCRTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @vcr unittest_annotation_test
     */
    public function testInterceptsWithAnnotations()
    {
        // Requests are intercepted and stored into  tests/fixtures/unittest_annotation_test.
        $result = file_get_contents('http://google.com');

        $this->assertEquals('This is a annotation test dummy.', $result, 'Call was not intercepted (using annotations).');

        // VCR is automatically turned on and off.
    }
}
```

## Installation

Simply run the following command:

``` bash
$ composer require --dev php-vcr/php-vcr
```

## Dependencies

PHP-VCR depends on:

  * PHP 5.3+
  * Curl extension
  * HTTP library [Guzzle](http://guzzlephp.org)
  * [symfony/yaml](https://github.com/symfony/yaml)
  * [beberlei/assert](https://github.com/beberlei/assert)

Composer installs all dependencies except extensions like curl.

## Run tests

In order to run all tests you need to get development dependencies using composer:

``` php
composer install --dev
phpunit ./tests
```

## Changelog

**The changelog has moved to the [PHP-VCR releases page](https://github.com/php-vcr/php-vcr/releases).**

Old changelog entries:

 * 2014-10-23 Release 1.1.6: #73, #74, #75, improvements for JSON storage and binary requests.
 * 2014-09-11 Release 1.1.5: Fixes #58 #60, #61, #69 updated vendors and new record mode.
 * 2014-04-26 Release 1.1.4: Fixes #50, #52, #53, #54, #56 and better error messages.
 * 2014-04-12 Release 1.1.3: Fixes #48: Allows data to be passed to CURLOPT_POSTFIELDS.
 * 2014-02-27 Release 1.1.2: Fix for storing the request body.
 * 2014-02-27 Release 1.1.1: Fix for non-GET requests with Guzzle.
 * 2014-02-22 Release 1.1.0: Removes curl runkit library hook and additional cleanup.
 * 2014-02-19 Release 1.0.7: Adds query request matcher.
 * 2014-01-12 Release 1.0.6: Updates dependencies.
 * 2013-10-13 Release 1.0.5: Fixed SOAP support, refactorings.
 * 2013-07-22 Release 1.0.4: Updates dependencies.
 * 2013-06-05 Release 1.0.3: Added curl_rewrite (in addition to curl_runkit) to overwrite curl functions.
 * 2013-05-15 Release 1.0.0
 * 2013-05-15 Adds PHPUnit annotations using [phpunit-testlistener-vcr](https://github.com/php-vcr/phpunit-testlistener-vcr)
 * 2013-05-14 Easier API (static method calls)
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
