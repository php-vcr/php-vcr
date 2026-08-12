<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Redaction;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Redaction\InvalidRedactionRuleException;
use VCR\Storage\Redaction\MissingSecretException;
use VCR\Storage\Redaction\SecretSource;

final class SecretSourceTest extends TestCase
{
    private const PLACEHOLDER = '{{API_KEY}}';

    /**
     * @return array<string,mixed>
     */
    private function recording(): array
    {
        return [
            'request' => ['method' => 'POST', 'url' => 'https://api.example.com/login'],
            'response' => ['status' => ['code' => 200, 'message' => 'OK']],
            'index' => 0,
        ];
    }

    public function testALiteralStringIsTheSecret(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, 'hunter2');

        $this->assertSame('hunter2', $source->resolve($this->recording()));
    }

    public function testAStringThatHappensToNameAFunctionIsStillTakenLiterally(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, 'strtoupper');

        $this->assertSame('strtoupper', $source->resolve($this->recording()));
    }

    public function testANoArgumentCallableIsInvokedWithoutTheRecording(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, static fn (): string => 'hunter2');

        $this->assertSame('hunter2', $source->resolve($this->recording()));
    }

    public function testARecordingAwareCallableReceivesTheRecording(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, static fn (array $recording): string => 'derived-'.$recording['request']['method']);

        $this->assertSame('derived-POST', $source->resolve($this->recording()));
    }

    public function testACallableWithOnlyAnUnrelatedOptionalParameterIsTreatedAsNoArgument(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, static fn (string $unrelated = 'hunter2'): string => $unrelated);

        $this->assertSame('hunter2', $source->resolve($this->recording()));
    }

    public function testAnInvokableObjectIsAcceptedAsACallable(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, new class {
            public function __invoke(): string
            {
                return 'hunter2';
            }
        });

        $this->assertSame('hunter2', $source->resolve($this->recording()));
    }

    public function testResolveThrowsMissingSecretExceptionForAnEmptyString(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, '');

        $this->expectException(MissingSecretException::class);
        $this->expectExceptionMessage(self::PLACEHOLDER);

        $source->resolve($this->recording());
    }

    public function testResolveThrowsMissingSecretExceptionForACallableReturningNull(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, static fn (): ?string => null);

        $this->expectException(MissingSecretException::class);

        $source->resolve($this->recording());
    }

    /**
     * `getenv()` returns false for an unset variable, so the documented
     * `filterSensitiveData('<<AUTH_TOKEN>>', getenv('API_TOKEN'))` shape has to degrade into a
     * MissingSecretException naming the placeholder rather than a raw TypeError.
     */
    public function testAFalseSourceResolvesToMissingSecretExceptionRatherThanATypeError(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, getenv('VCR_TEST_DEFINITELY_UNSET_VARIABLE'));

        $this->expectException(MissingSecretException::class);
        $this->expectExceptionMessage(self::PLACEHOLDER);

        $source->resolve($this->recording());
    }

    public function testANullSourceResolvesToMissingSecretException(): void
    {
        $source = new SecretSource(self::PLACEHOLDER, null);

        $this->expectException(MissingSecretException::class);

        $source->resolve($this->recording());
    }

    /**
     * @dataProvider unsupportedSourceProvider
     */
    public function testConstructionRejectsASourceThatIsNeitherStringNorCallable(mixed $source, string $expectedType): void
    {
        $this->expectException(InvalidRedactionRuleException::class);
        $this->expectExceptionMessage($expectedType);

        new SecretSource(self::PLACEHOLDER, $source);
    }

    /**
     * @return array<string,array{0:mixed,1:string}>
     */
    public static function unsupportedSourceProvider(): array
    {
        return [
            'int' => [42, 'int'],
            'true' => [true, 'bool'],
            'float' => [1.5, 'float'],
            'array' => [['not', 'callable'], 'array'],
            'object' => [new \stdClass(), 'stdClass'],
        ];
    }
}
