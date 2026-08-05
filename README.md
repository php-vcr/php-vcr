![PHP-VCR](https://user-images.githubusercontent.com/133832/27151811-0d95c6c4-514c-11e7-834e-eff1eec2ea16.png)

[![Continuous Integration](https://github.com/php-vcr/php-vcr/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/php-vcr/php-vcr/actions/workflows/ci.yml)
[![Code Coverage](https://scrutinizer-ci.com/g/php-vcr/php-vcr/badges/coverage.png?s=15cf1644c8cf37a868e03cfba809a5e24c78f285)](https://scrutinizer-ci.com/g/php-vcr/php-vcr/)
[![Latest Version](https://img.shields.io/packagist/v/php-vcr/php-vcr.svg)](https://packagist.org/packages/php-vcr/php-vcr)
[![PHP Version](https://img.shields.io/badge/php-%5E8.0-777bb4.svg)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
[![Context7](https://img.shields.io/badge/Context7-Docs-blue)](https://context7.com/php-vcr/php-vcr)

This is a port of the [VCR](http://github.com/vcr/vcr) Ruby library to PHP.

Record your test suite's HTTP interactions and replay them during future test runs for fast, deterministic, accurate tests. Full documentation lives at [php-vcr.github.io/php-vcr](https://php-vcr.github.io/php-vcr/) — or browse the raw Markdown in [`docs/`](docs/index.md).

Disclaimer: Doing this in PHP is not as easy as in programming languages which support monkey patching (I'm looking at you, Ruby)

## Features

* Automatically records and replays your HTTP(s) interactions with minimal setup/configuration code.
* Supports common http functions and extensions — see [Supported HTTP libraries](#supported-http-libraries) below
* The same request can receive different responses in different tests -- just use different cassettes.
* Disables all HTTP requests that you don't explicitly allow by [setting the record mode](docs/guides/record-modes.md)
* [Request matching](docs/guides/request-matching.md) is configurable based on HTTP method, URI, host, path, body and headers, or you can easily
  implement a custom request matcher to handle any need.
* The recorded requests and responses are stored on disk in a serialization format of your choice
  (currently YAML and JSON are built in)

## Usage example

> **⚠️ Turn VCR on as soon as possible** — right after Composer's autoloader, before any code that calls
> `curl_*` or uses `SoapClient` **gets loaded**. That call is what registers the interception; turn it back
> off right afterwards (`VCR::turnOn(); VCR::turnOff();` in your bootstrap file) if you don't want hooks live
> for the whole run — each test can cheaply call `turnOn()` again only when it actually wants a cassette.
> Details: [How VCR works](docs/guides/how-vcr-works.md).

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
}
```

Forgetting to insert a cassette throws immediately, instead of silently hitting the network:

``` php
public function testShouldThrowExceptionIfNoCasettePresent()
{
    $this->expectException(\BadMethodCallException::class);
    $this->expectExceptionMessage(
        "Invalid http request. No cassette inserted. Please make sure to insert "
        . "a cassette in your unit test using VCR::insertCassette('name');"
    );
    \VCR\VCR::turnOn();
    file_get_contents('http://example.com');
}
```

## Supported HTTP libraries

All three hooks (`stream_wrapper`, `curl`, `soap`) are **enabled by default** when you call `VCR::turnOn()`.
Full interception details and how to enable only specific hooks: [Library Hooks](docs/reference/library-hooks.md).

## Record modes

The record mode controls how VCR behaves when a cassette is inserted: `new_episodes` (default), `once`,
`none`, `all`. Full behaviour per mode: [Record Modes](docs/guides/record-modes.md).

## Recording identical requests

By default php-vcr records identical requests separately and replays them in the same order they were made.
Details and how to change this: [Cassettes → identical requests](docs/guides/cassettes.md#identical-requests).

## Installation

Simply run the following command:

``` bash
composer require --dev php-vcr/php-vcr
```

## Dependencies

PHP-VCR depends on PHP 8 and the curl extension, plus a few Composer packages Composer installs for you. Full
requirements and the tested HTTP library matrix: [Requirements](docs/requirements.md).

## Documentation

Full documentation — searchable, versioned, dark mode — lives at
[php-vcr.github.io/php-vcr](https://php-vcr.github.io/php-vcr/). Or browse the raw Markdown directly in
[`docs/`](docs/index.md):

* [Getting Started](docs/getting-started.md) · [How VCR works](docs/guides/how-vcr-works.md)
* [Cassettes](docs/guides/cassettes.md) · [Record Modes](docs/guides/record-modes.md) · [Request Matching](docs/guides/request-matching.md)
* How-to: [PHPUnit](docs/howto/use-with-phpunit.md) · [Codeception](docs/howto/use-with-codeception.md) · [Filter sensitive data](docs/howto/filter-sensitive-data.md) · [SOAP](docs/howto/record-soap.md) · [Storage factory / custom storage](docs/howto/custom-storage.md)
* Reference: [Configuration](docs/reference/configuration.md) · [Events](docs/reference/events.md) · [Storage backends](docs/reference/storage-backends.md)

## Contributing

Bug reports, feature requests and pull requests are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) for how
this repository is set up, the pre-push checks, and why documentation is part of every change.

## Run tests

In order to run all tests you need to get development dependencies using composer:

``` bash
composer install
composer test
```

## Changelog

**The changelog has moved to the [PHP-VCR releases page](https://github.com/php-vcr/php-vcr/releases).**

[Old changelog entries](docs/old-changelog.md)

## Copyright

Copyright (c) 2013-2026 Adrian Philipp. Released under the terms of the MIT license. See LICENSE for details.
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
