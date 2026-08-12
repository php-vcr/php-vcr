<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use VCR\Storage\RecordingPath;

final class RecordingPathTest extends TestCase
{
    /**
     * @return array<string,mixed>
     */
    private function recording(): array
    {
        return [
            'request' => [
                'method' => 'POST',
                'url' => 'https://api.example.com/login',
                'headers' => [
                    'Host' => 'api.example.com',
                    'authorization' => 'Bearer secret',
                    'X-Trace-Id' => 'abc-123',
                ],
                'body' => '{"password":"hunter2"}',
                'post_fields' => ['user' => 'alice', 'password' => 'hunter2'],
            ],
            'response' => [
                'status' => ['code' => 200, 'message' => 'OK'],
                'headers' => ['Set-Cookie' => 'session=abc', 'Content-Type' => 'application/json'],
                'body' => 'welcome',
            ],
            'index' => 0,
        ];
    }

    public function testResolvePathsSplitsDottedFieldPaths(): void
    {
        $paths = RecordingPath::resolvePaths($this->recording(), ['request.body', 'response.body'], []);

        $this->assertSame([['request', 'body'], ['response', 'body']], $paths);
    }

    public function testResolvePathsMatchesHeaderNamesCaseInsensitively(): void
    {
        $paths = RecordingPath::resolvePaths($this->recording(), [], ['AUTHORIZATION']);

        $this->assertSame([['request', 'headers', 'authorization']], $paths);
    }

    public function testResolvePathsMatchesHeadersInBothContainers(): void
    {
        $paths = RecordingPath::resolvePaths($this->recording(), [], ['authorization', 'set-cookie']);

        $this->assertSame(
            [['request', 'headers', 'authorization'], ['response', 'headers', 'Set-Cookie']],
            $paths
        );
    }

    public function testResolvePathsKeepsHeaderNamesContainingDotsAsOneSegment(): void
    {
        $recording = $this->recording();
        $recording['request']['headers']['X.Api.Secret'] = 'top-secret';

        $paths = RecordingPath::resolvePaths($recording, [], ['X.Api.Secret']);

        $this->assertSame([['request', 'headers', 'X.Api.Secret']], $paths);
    }

    public function testResolvePathsIgnoresNonArrayHeaders(): void
    {
        $recording = ['request' => ['headers' => 'not-an-array'], 'index' => 0];

        $paths = RecordingPath::resolvePaths($recording, [], ['authorization']);

        $this->assertSame([], $paths);
    }

    public function testResolvePathsReturnsEmptyArrayForEmptyInputs(): void
    {
        $paths = RecordingPath::resolvePaths($this->recording(), [], []);

        $this->assertSame([], $paths);
    }

    public function testReplaceTransformsTheValueAtTheSegmentPath(): void
    {
        $recording = $this->recording();

        $replaced = RecordingPath::replace($recording, ['request', 'body'], static fn ($value) => strtoupper((string) $value));

        $this->assertSame('{"PASSWORD":"HUNTER2"}', $replaced['request']['body']);
    }

    public function testReplaceLeavesTheRecordingUnchangedWhenASegmentIsMissing(): void
    {
        $recording = ['request' => ['method' => 'GET'], 'index' => 0];

        $replaced = RecordingPath::replace($recording, ['request', 'body'], static fn ($value) => 'never-called');

        $this->assertSame($recording, $replaced);
    }

    public function testReplaceLeavesTheRecordingUnchangedWhenTheContainerIsMissing(): void
    {
        $recording = ['index' => 0];

        $replaced = RecordingPath::replace($recording, ['request', 'body'], static fn ($value) => 'never-called');

        $this->assertSame($recording, $replaced);
    }

    public function testReplaceSupportsOpaqueHeaderNameSegmentsContainingDots(): void
    {
        $recording = $this->recording();
        $recording['request']['headers']['X.Api.Secret'] = 'top-secret';

        $replaced = RecordingPath::replace(
            $recording,
            ['request', 'headers', 'X.Api.Secret'],
            static fn ($value) => strtoupper((string) $value)
        );

        $this->assertSame('TOP-SECRET', $replaced['request']['headers']['X.Api.Secret']);
    }

    public function testGetReadsTheValueAtTheSegmentPath(): void
    {
        $value = RecordingPath::get($this->recording(), ['request', 'body']);

        $this->assertSame('{"password":"hunter2"}', $value);
    }

    public function testGetReadsNestedArrayValues(): void
    {
        $value = RecordingPath::get($this->recording(), ['response', 'status', 'code']);

        $this->assertSame(200, $value);
    }

    public function testGetReturnsNullWhenASegmentIsMissing(): void
    {
        $value = RecordingPath::get(['request' => ['method' => 'GET']], ['request', 'body']);

        $this->assertNull($value);
    }

    public function testGetReturnsNullWhenTheContainerIsMissing(): void
    {
        $value = RecordingPath::get(['index' => 0], ['request', 'body']);

        $this->assertNull($value);
    }

    public function testGetSupportsOpaqueHeaderNameSegmentsContainingDots(): void
    {
        $recording = $this->recording();
        $recording['request']['headers']['X.Api.Secret'] = 'top-secret';

        $value = RecordingPath::get($recording, ['request', 'headers', 'X.Api.Secret']);

        $this->assertSame('top-secret', $value);
    }
}
