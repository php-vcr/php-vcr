<?php

namespace VCR;

use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    /**
     * @var Configuration
     */
    private $config;

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
        $this->expectException(\VCR\VCRException::class);
        $this->expectExceptionMessage("A request matchers name must be at least one character long. Found ''");
        $expected = function ($first, $second) {
            return true;
        };
        $this->config->addRequestMatcher('', $expected);
    }

    public function testAddRequestMatchers(): void
    {
        $expected = function () {
            return true;
        };
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
        $this->expectException(\VCR\VCRException::class);
        $this->expectExceptionMessage("Storage 'Does not exist' not available.");
        $this->config->setStorage('Does not exist');
    }

    public function testGetStorage(): void
    {
        $class = $this->config->getStorage();
        $this->assertContains('Iterator', class_implements($class));
        $this->assertContains('Traversable', class_implements($class));
        $this->assertContains('VCR\Storage\AbstractStorage', class_parents($class));
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
        $this->expectException(\VCR\VCRException::class);
        $this->expectExceptionMessage("Mode 'invalid' does not exist.");
        $this->config->setMode('invalid');
    }

    public function testAddRedactionFailsWithNoToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Redaction replacement string must be a non-empty string or callable.");
        $this->config->addRedaction('', 'secret123');
    }

    public function testAddRedactionWithString(): void
    {
        $this->config->addRedaction('<PASSWORD>', 'secret123');
        $filters = $this->config->getRedactions();
        $this->assertCount(1, $filters);
        $this->assertArrayHasKey('<PASSWORD>', $filters);
        $this->assertIsCallable($filters['<PASSWORD>']);

        $request = new \VCR\Request('GET', 'http://example.com');
        $response = new \VCR\Response(200, [], 'body');
        $this->assertEquals('secret123', $filters['<PASSWORD>']($request, $response));
    }

    public function testAddRedactionWithCallable(): void
    {
        $expected = function ($request, $response) {
            return 'secret123';
        };

        $this->config->addRedaction('<PASSWORD>', $expected);
        $filters = $this->config->getRedactions();
        $this->assertEquals($expected, $filters['<PASSWORD>']);
    }
}
