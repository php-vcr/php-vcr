<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Util;

use PHPUnit\Framework\TestCase;
use VCR\Util\SymfonyHttpClientHelper;

class SymfonyHttpClientHelperTest extends TestCase
{
    /**
     * @dataProvider expectsNoContentProvider
     */
    public function testExpectsNoContent(?string $privateData, bool $expected): void
    {
        $ch = curl_init('http://example.com');

        if (null !== $privateData) {
            curl_setopt($ch, \CURLOPT_PRIVATE, $privateData);
        }

        $this->assertSame($expected, SymfonyHttpClientHelper::expectsNoContent($ch));

        curl_close($ch);
    }

    /**
     * @return array<array{?string, bool}>
     */
    public static function expectsNoContentProvider(): array
    {
        return [
            'done state with retry 0' => ['_0', true],
            'done state with retry 1' => ['_1', true],
            'done state with retry 9' => ['_9', true],
            'headers state' => ['H0', false],
            'content state' => ['C0', false],
            'null value (not set)' => [null, false],
            'empty string' => ['', false],
            'invalid format' => ['invalid', false],
            'only underscore' => ['_', true],
        ];
    }

    public function testExpectsNoContentWithSnapshot(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, 'H0'); // Set to H0 on handle

        // Snapshot overrides handle value
        $this->assertTrue(SymfonyHttpClientHelper::expectsNoContent($ch, '_0'));
        $this->assertFalse(SymfonyHttpClientHelper::expectsNoContent($ch, 'C0'));

        curl_close($ch);
    }

    public function testTransitionToContentPhaseFromHeadersState(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, 'H0');

        SymfonyHttpClientHelper::transitionToContentPhase($ch, 'H0');

        $result = curl_getinfo($ch, \CURLINFO_PRIVATE);
        $this->assertSame('C0', $result);

        curl_close($ch);
    }

    public function testTransitionToContentPhaseFromHeadersStateWithRetry(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, 'H3');

        SymfonyHttpClientHelper::transitionToContentPhase($ch, 'H3');

        $result = curl_getinfo($ch, \CURLINFO_PRIVATE);
        $this->assertSame('C3', $result);

        curl_close($ch);
    }

    public function testTransitionToContentPhaseDoesNothingWhenAlreadyInContentState(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, 'C0');

        SymfonyHttpClientHelper::transitionToContentPhase($ch, 'C0');

        $result = curl_getinfo($ch, \CURLINFO_PRIVATE);
        $this->assertSame('C0', $result);

        curl_close($ch);
    }

    public function testTransitionToContentPhaseDoesNothingWhenInDoneState(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, '_0');

        SymfonyHttpClientHelper::transitionToContentPhase($ch, '_0');

        $result = curl_getinfo($ch, \CURLINFO_PRIVATE);
        $this->assertSame('_0', $result);

        curl_close($ch);
    }

    public function testTransitionToContentPhaseHandlesNotSet(): void
    {
        $ch = curl_init('http://example.com');
        // Don't set CURLOPT_PRIVATE at all

        SymfonyHttpClientHelper::transitionToContentPhase($ch);

        // Should not crash, no transition occurs
        $result = curl_getinfo($ch, \CURLINFO_PRIVATE);
        $this->assertFalse($result, 'PRIVATE should remain unset');

        curl_close($ch);
    }

    public function testTransitionToContentPhaseWithSnapshot(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, 'C0'); // Set to C0 on handle

        // Snapshot says it's H2, so transition should happen
        SymfonyHttpClientHelper::transitionToContentPhase($ch, 'H2');

        $result = curl_getinfo($ch, \CURLINFO_PRIVATE);
        $this->assertSame('C2', $result, 'Should transition based on snapshot value');

        curl_close($ch);
    }

    public function testGetPrivateDataReturnsNullWhenNotSet(): void
    {
        $ch = curl_init('http://example.com');

        $result = SymfonyHttpClientHelper::getPrivateData($ch);

        $this->assertNull($result);

        curl_close($ch);
    }

    public function testGetPrivateDataReturnsValueWhenSet(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, 'H5');

        $result = SymfonyHttpClientHelper::getPrivateData($ch);

        $this->assertSame('H5', $result);

        curl_close($ch);
    }

    public function testGetPrivateDataPrefersSnapshot(): void
    {
        $ch = curl_init('http://example.com');
        curl_setopt($ch, \CURLOPT_PRIVATE, 'C0');

        $result = SymfonyHttpClientHelper::getPrivateData($ch, 'H3');

        $this->assertSame('H3', $result, 'Snapshot should override handle value');

        curl_close($ch);
    }
}
