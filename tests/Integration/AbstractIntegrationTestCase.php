<?php

declare(strict_types=1);

namespace VCR\Tests\Integration;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use VCR\Storage\YamlStorageFactory;
use VCR\VCR;

abstract class AbstractIntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        vfsStream::setup('testDir');

        // Reset to the real Configuration defaults before every test, not just cassette path/hooks:
        // VCR::configure() is a process-wide singleton, so a test that narrows the mode, the storage
        // factory, or the request matchers would otherwise leak that into every test that runs after it
        // in the same PHPUnit process.
        VCR::configure()
            ->setCassettePath(vfsStream::url('testDir'))
            ->enableLibraryHooks(['stream_wrapper', 'curl', 'soap'])
            ->setMode(VCR::MODE_NEW_EPISODES)
            ->setStorageFactory(new YamlStorageFactory())
            ->enableRequestMatchers([
                'method', 'url', 'host', 'headers', 'body', 'post_fields', 'query_string', 'soap_operation',
            ]);
    }

    protected function tearDown(): void
    {
        VCR::turnOff();
    }
}
