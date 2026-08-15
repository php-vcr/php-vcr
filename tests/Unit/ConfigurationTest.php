<?php

declare(strict_types=1);

namespace VCR\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VCR\Configuration;
use VCR\Request;
use VCR\Storage\BlackholeStorageFactory;
use VCR\Storage\JsonStorageFactory;
use VCR\Storage\StorageFactoryInterface;
use VCR\Storage\StorageInterface;
use VCR\Storage\YamlStorageFactory;
use VCR\VCR;
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

    public function testEnableBodyJsonRequestMatcher(): void
    {
        $this->config->enableRequestMatchers(['body_json']);
        $this->assertEquals(
            [
                ['VCR\RequestMatcher', 'matchBodyJson'],
            ],
            $this->config->getRequestMatchers()
        );
    }

    /**
     * The `body_json` matcher joins the default matcher set, where it is ANDed with
     * `body`. Because it can only ever accept what `body` already accepts, the
     * default matching outcome must stay exactly as it was before it existed.
     *
     * @dataProvider defaultMatcherSetBodyProvider
     */
    public function testDefaultMatcherSetOutcomeIsUnchangedByBodyJson(?string $storedBody, ?string $body): void
    {
        $matchersWithoutBodyJson = [
            'method', 'url', 'host', 'headers', 'body', 'post_fields', 'query_string', 'soap_operation',
        ];

        $storedRequest = new Request('POST', 'http://example.com', []);
        $request = new Request('POST', 'http://example.com', []);

        if (null !== $storedBody) {
            $storedRequest->setBody($storedBody);
        }

        if (null !== $body) {
            $request->setBody($body);
        }

        $withBodyJson = $storedRequest->matches($request, $this->config->getRequestMatchers());

        $this->config->enableRequestMatchers($matchersWithoutBodyJson);
        $withoutBodyJson = $storedRequest->matches($request, $this->config->getRequestMatchers());

        $this->assertSame($withoutBodyJson, $withBodyJson);
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function defaultMatcherSetBodyProvider(): array
    {
        return [
            'identical bodies' => ['{"a":1,"b":2}', '{"a":1,"b":2}'],
            'reordered object keys' => ['{"a":1,"b":2}', '{"b":2,"a":1}'],
            'reordered array elements' => ['["a","b"]', '["b","a"]'],
            'different bodies' => ['{"a":1}', '{"a":2}'],
            'invalid json' => ['{"a":1', '{"a":1'],
            'non json bodies' => ['plain text', 'plain text'],
            'absent bodies' => [null, null],
        ];
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
        $expected = static fn ($first, $second) => true;
        $this->config->addRequestMatcher('', $expected);
    }

    public function testAddRequestMatchers(): void
    {
        $expected = static fn () => true;
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
    public static function availableStorageProvider(): array
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

    public function testDefaultStorageFactoryIsYaml(): void
    {
        $this->assertInstanceOf(YamlStorageFactory::class, $this->config->getStorageFactory());
    }

    public function testSetStorageFactoryIsFluentAndReturnsTheSameInstance(): void
    {
        $factory = new JsonStorageFactory();

        $this->assertSame($this->config, $this->config->setStorageFactory($factory));
        $this->assertSame($factory, $this->config->getStorageFactory());
    }

    public function testSetStorageSelectsTheMatchingBuiltinFactory(): void
    {
        $this->config->setStorage('blackhole');

        $this->assertInstanceOf(BlackholeStorageFactory::class, $this->config->getStorageFactory());
    }

    public function testGetStorageThrowsForACustomStorageFactory(): void
    {
        $this->config->setStorageFactory(new class implements StorageFactoryInterface {
            public function create(string $cassettePath, string $cassetteName): StorageInterface
            {
                throw new \LogicException('Not needed for this test.');
            }
        });

        $this->expectException(VCRException::class);
        $this->expectExceptionMessage('Please use getStorageFactory() instead.');

        $this->config->getStorage();
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

    public function testSetModeAllIsAccepted(): void
    {
        $this->config->setMode(VCR::MODE_ALL);
        $this->assertSame(VCR::MODE_ALL, $this->config->getMode());
    }

    public function testRecordIdenticalRequestsDefaultsToTrue(): void
    {
        $this->assertTrue($this->config->getRecordIdenticalRequests());
    }

    public function testSetRecordIdenticalRequests(): void
    {
        $this->config->setRecordIdenticalRequests(false);
        $this->assertFalse($this->config->getRecordIdenticalRequests());
    }
}
