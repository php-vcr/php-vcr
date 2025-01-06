<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VCR\Configuration;
use VCR\VCRException;

final class ConfigurationTest extends TestCase
{
    private Configuration $config;

    protected function setUp(): void
    {
        $this->config = new Configuration();
    }

    public function testSetCassettePathThrowsErrorOnInvalidPath(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage(
            "Cassette path 'invalid_path' is not a directory. Please either "
            .'create it or set a different cassette path using '
            ."\\VCR\\VCR::configure()->setCassettePath('directory')."
        );
        $this->config->setCassettePath('invalid_path');
    }

    public function testGetLibraryHooks(): void
    {
        $this->assertEquals(
            [
                'VCR\LibraryHooks\StreamWrapperHook',
                'VCR\LibraryHooks\CurlHook',
                'VCR\LibraryHooks\SoapHook',
            ],
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableLibraryHooks(): void
    {
        $this->config->enableLibraryHooks(['stream_wrapper']);
        $this->assertEquals(
            [
                'VCR\LibraryHooks\StreamWrapperHook',
            ],
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableSingleLibraryHook(): void
    {
        $this->config->enableLibraryHooks('stream_wrapper');
        $this->assertEquals(
            [
                'VCR\LibraryHooks\StreamWrapperHook',
            ],
            $this->config->getLibraryHooks()
        );
    }

    public function testEnableLibraryHooksFailsWithWrongHookName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Library hooks don't exist: non_existing");
        $this->config->enableLibraryHooks(['non_existing']);
    }

    public function testEnableRequestMatchers(): void
    {
        $this->config->enableRequestMatchers(['body', 'headers']);
        $this->assertEquals(
            [
                ['VCR\RequestMatcher', 'matchHeaders'],
                ['VCR\RequestMatcher', 'matchBody'],
            ],
            $this->config->getRequestMatchers()
        );
    }

    public function testEnableRequestMatchersFailsWithNoExistingName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Request matchers don't exist: wrong, name");
        $this->config->enableRequestMatchers(['wrong', 'name']);
    }

    public function testAddRequestMatcherFailsWithNoName(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage("A request matchers name must be at least one character long. Found ''");
        $expected = fn ($first, $second) => true;
        $this->config->addRequestMatcher('', $expected);
    }

    public function testAddRequestMatchers(): void
    {
        $expected = fn () => true;
        $this->config->addRequestMatcher('new_matcher', $expected);
        $this->assertContains($expected, $this->config->getRequestMatchers());
    }

    /**
     * @dataProvider availableStorageProvider
     */
    public function testSetStorage(string $name, string $className): void
    {
        $this->config->setStorage($name);
        $this->assertEquals($className, $this->config->getStorage(), "$name should be class $className.");
    }

    /** @return array<string[]> */
    public function availableStorageProvider(): array
    {
        return [
            ['json', 'VCR\Storage\Json'],
            ['yaml', 'VCR\Storage\Yaml'],
        ];
    }

    public function testSetStorageInvalidName(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage("Storage 'Does not exist' not available.");
        $this->config->setStorage('Does not exist');
    }

    public function testGetStorage(): void
    {
        $class = $this->config->getStorage();
        $this->assertContains('Iterator', (array) class_implements($class));
        $this->assertContains('Traversable', (array) class_implements($class));
        $this->assertContains('VCR\Storage\AbstractStorage', (array) class_parents($class));
    }

    public function testWhitelist(): void
    {
        $expected = ['Tux', 'Gnu'];

        $this->config->setWhiteList($expected);

        $this->assertEquals($expected, $this->config->getWhiteList());
    }

    public function testBlacklist(): void
    {
        $expected = ['Tux', 'Gnu'];

        $this->config->setBlackList($expected);

        $this->assertEquals($expected, $this->config->getBlackList());
    }

    public function testSetModeInvalidName(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage("Mode 'invalid' does not exist.");
        $this->config->setMode('invalid');
    }
}
