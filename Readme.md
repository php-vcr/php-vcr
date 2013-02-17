# PHPVCR

This is a port of [VCR](http://github.com/vcr/vcr) for ruby.

Record your test suite's HTTP interactions and replay them during future test runs for fast, deterministic, accurate tests.

## Features

* Automatically records and replays your HTTP interactions with minimal setup/configuration code.
* Supports common http functions and extensions
  following are supported:
  * everyting using [streamWrapper](http://php.net/manual/en/class.streamwrapper.php): fopen(), fread(),file_get_contents(), ...
  * curl(), curl_multi() using runkit extension
* Todo: Request matching is configurable based on HTTP method, URI, host, path, body and headers, or you can easily
  implement a custom request matcher to handle any need.
* Todo: The same request can receive different responses in different tests--just use different cassettes.
* Todo: The recorded requests and responses are stored on disk in a serialization format of your choice
  (currently YAML and JSON are built in, and you can easily implement your own custom serializer)
  and can easily be inspected and edited.
* Todo: Automatically re-records cassettes on a configurable regular interval to keep them fresh and current.
* Todo: Disables all HTTP requests that you don't explicitly allow.
* Todo: Supports PHPUnit annotations

## Usage example

``` php
class VCRTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->vcr = new Adri\VCR;
    }

    public function testHttpStreamWrapper()
    {
        $this->vcr->insertCassette('example');
        // some http call, for example:
        $result = file_get_contents('http://example.com');
        $this->assertNotEmpty($result);
    }

}
```

## Installation

There is no release yet, sorry.

``` bash
git clone git@github.com:adri/phpvc
cd phpvcr
composer update
phpunit tests
```

## Dependencies

PHPVCR installs needed (except runkit) depenencies using composer. Dependencies are:

  * [Guzzle](http://guzzlephp.org)
  * > PHP 5.3
  * (optional) runkit extension if you want to intercept curl

## Run tests

``` php
phpunit ./tests
```
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
