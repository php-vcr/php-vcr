![PHP-VCR](https://user-images.githubusercontent.com/133832/27151811-0d95c6c4-514c-11e7-834e-eff1eec2ea16.png)

[![Continuous Integration](https://github.com/php-vcr/php-vcr/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/php-vcr/php-vcr/actions/workflows/ci.yml)
[![Code Coverage](https://scrutinizer-ci.com/g/php-vcr/php-vcr/badges/coverage.png?s=15cf1644c8cf37a868e03cfba809a5e24c78f285)](https://scrutinizer-ci.com/g/php-vcr/php-vcr/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/php-vcr/php-vcr/badges/quality-score.png?s=4f638dbca5eb51fb9c87a1dd45c5df94687d85bd)](https://scrutinizer-ci.com/g/php-vcr/php-vcr/)

This is a port of the [VCR](http://github.com/vcr/vcr) Ruby library to PHP.

Record your test suite's HTTP interactions and replay them during future test runs for fast, deterministic, accurate tests. A bit of documentation can be found on the [php-vcr website](http://php-vcr.github.io).

Disclaimer: Doing this in PHP is not as easy as in programming languages which support monkey patching (I'm looking at you, Ruby)

## Features

* Automatically records and replays your HTTP(s) interactions with minimal setup/configuration code.
* Supports common http functions and extensions — see [Supported HTTP libraries](#supported-http-libraries) below
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
class VCRTest extends TestCase
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
class VCRTest extends TestCase
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

## Supported HTTP libraries

All three hooks are **enabled by default** when you call `VCR::turnOn()`. No extra configuration is required.

| Hook name | Intercepted libraries | How it works |
|---|---|---|
| `stream_wrapper` | `fopen()`, `fread()`, `file_get_contents()`, `Symfony\Component\HttpClient\NativeHttpClient` | Replaces the `http`/`https` stream wrapper — no source rewriting |
| `curl` | `curl_*` functions, `Symfony\Component\HttpClient\CurlHttpClient`, Guzzle (curl backend) | Rewrites `curl_*` calls in loaded PHP source via a stream filter |
| `soap` | `SoapClient` | Rewrites `new SoapClient(…)` in loaded PHP source via a stream filter |

To enable only specific hooks (e.g. when you know your code only uses curl):

```php
\VCR\VCR::configure()->enableLibraryHooks(['curl']);
\VCR\VCR::turnOn();
```

Available hook names: `stream_wrapper`, `curl`, `soap`.

## Record modes

The record mode controls how VCR behaves when a cassette is inserted.

| Mode | Constant | Behaviour |
|---|---|---|
| `new_episodes` | `VCR::MODE_NEW_EPISODES` | **Default.** Plays back recorded interactions; performs real HTTP for any request that has no recording and records the response. |
| `once` | `VCR::MODE_ONCE` | Plays back recorded interactions. Allows new requests only when the cassette is new (first run). Throws on a miss after that. |
| `none` | `VCR::MODE_NONE` | Read-only. Plays back recorded interactions only. Throws on any request that has no recording. |
| `all` | `VCR::MODE_ALL` | **Re-record mode.** Never plays back. Always performs the real HTTP request and records the response fresh. The cassette is purged on insert so every run starts clean. |

```php
// Re-record an existing cassette from scratch
\VCR\VCR::configure()->setMode(\VCR\VCR::MODE_ALL);
\VCR\VCR::turnOn();
\VCR\VCR::insertCassette('my_cassette'); // existing recordings are purged
file_get_contents('http://example.com');  // hits network, recorded fresh
\VCR\VCR::eject();
\VCR\VCR::turnOff();
```

> **Note:** A run under `MODE_ALL` that performs no HTTP request leaves an empty cassette — this is inherent to the fresh re-record semantics.

## Recording identical requests

By default php-vcr records identical requests separately and replays them in
the same order they were made (each gets an incrementing `index` in the
cassette). If your test issues an identical request a variable number of times
across runs, disable this so every identical request replays the first
recorded response:

```php
\VCR\VCR::configure()->setRecordIdenticalRequests(false);
```

## Installation

Simply run the following command:

``` bash
$ composer require --dev php-vcr/php-vcr
```

## Dependencies

PHP-VCR depends on:

  * PHP 8
  * Curl extension
  * [symfony/event-dispatcher](https://github.com/symfony/event-dispatcher)
  * [symfony/yaml](https://github.com/symfony/yaml)
  * [beberlei/assert](https://github.com/beberlei/assert)

Composer installs all dependencies except extensions like curl.

## Run tests

In order to run all tests you need to get development dependencies using composer:

``` php
composer install
composer test
```

## Changelog

**The changelog has moved to the [PHP-VCR releases page](https://github.com/php-vcr/php-vcr/releases).**

[Old changelog entries](docs/old-changelog.md)

## Copyright
Copyright (c) 2013-2023 Adrian Philipp. Released under the terms of the MIT license. See LICENSE for details.
[Contributors](https://github.com/php-vcr/php-vcr/graphs/contributors)

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
