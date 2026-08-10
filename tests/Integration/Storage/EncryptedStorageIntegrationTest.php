<?php

declare(strict_types=1);

namespace VCR\Tests\Integration\Storage;

use org\bovigo\vfs\vfsStream;
use VCR\LibraryHooks\StreamWrapperHook;
use VCR\Storage\EncryptedStorageFactory;
use VCR\Storage\Encryption\DecryptionFailedException;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\Storage\Encryption\EncryptionPolicy;
use VCR\Storage\JsonStorageFactory;
use VCR\Storage\YamlStorageFactory;
use VCR\Tests\Integration\AbstractHttpServerIntegrationTestCase;
use VCR\VCR;

final class EncryptedStorageIntegrationTest extends AbstractHttpServerIntegrationTestCase
{
    private const SECRET = 'hunter2-do-not-commit';

    private EncryptionKey $key;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\extension_loaded('sodium')) {
            $this->markTestSkipped('The encrypted storage requires ext-sodium, which is not loaded.');
        }

        $this->key = EncryptionKey::fromBinary(str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES));

        VCR::configure()
            ->setStorageFactory(EncryptedStorageFactory::withKey(new YamlStorageFactory(), $this->key))
            ->enableRequestMatchers(['method', 'url', 'body', 'post_fields']);
    }

    private function post(): int
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAuthorization: Bearer ".self::SECRET."\r\n",
                'content' => http_build_query(['password' => self::SECRET]),
                'ignore_errors' => true,
            ],
        ]);

        return $this->performAndReadStatus(self::$baseUrl.'/post', $context);
    }

    private function get(): int
    {
        return $this->performAndReadStatus(self::$baseUrl.'/get?token='.self::SECRET, stream_context_create());
    }

    /**
     * @param resource $context
     */
    private function performAndReadStatus(string $url, $context): int
    {
        $stream = fopen($url, 'r', false, $context);

        if (false === $stream) {
            throw new \RuntimeException(\sprintf('Unable to open a stream to "%s".', $url));
        }

        // StreamWrapperHook::streamGetMetaData() reports the real response header lines whether
        // the stream was served by VCR's own wrapper (replay) or PHP's native http wrapper
        // (recording, since library hooks are disabled around the real outbound request).
        $meta = StreamWrapperHook::streamGetMetaData($stream);
        fclose($stream);

        /** @var string[] $responseHeaders */
        $responseHeaders = \is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : [];

        return $this->statusFromResponseHeaders($responseHeaders);
    }

    /**
     * @param string[] $responseHeaders
     */
    private function statusFromResponseHeaders(array $responseHeaders): int
    {
        if (!isset($responseHeaders[0]) || !preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0], $matches)) {
            throw new \RuntimeException('Unable to determine the HTTP status code from the response headers.');
        }

        return (int) $matches[1];
    }

    private function cassettePath(string $cassette): string
    {
        return vfsStream::url('testDir').'/'.$cassette;
    }

    private function cassetteContents(string $cassette): string
    {
        return (string) file_get_contents($this->cassettePath($cassette));
    }

    public function testRecordsCiphertextAndReplaysPlaintext(): void
    {
        $this->recordAndReplay('encrypted-post', fn (): int => $this->post());

        $raw = $this->cassetteContents('encrypted-post');

        $this->assertStringNotContainsString(self::SECRET, $raw, 'The secret must never hit the disk.');
        $this->assertStringContainsString('vcr:enc:v1:', $raw);
    }

    public function testUrlStaysReadableForReview(): void
    {
        $this->recordAndReplay('readable-post', fn (): int => $this->post());

        $this->assertStringContainsString('/post', $this->cassetteContents('readable-post'));
    }

    public function testReplayWorksWithBodyMatchersEnabled(): void
    {
        VCR::configure()->enableRequestMatchers(['method', 'url', 'body', 'post_fields', 'headers']);

        $this->recordAndReplay('matcher-post', fn (): int => $this->post());

        $this->addToAssertionCount(1);
    }

    public function testReRecordingProducesBytewiseIdenticalCassettes(): void
    {
        $this->recordAndReplay('deterministic-post', fn (): int => $this->post());
        preg_match_all('/vcr:enc:v1:[A-Za-z0-9+\/=]+/', $this->cassetteContents('deterministic-post'), $first);

        // A distinct cassette name avoids VCRFactory's per-name Storage cache, same reasoning as
        // testReplayingWithTheWrongKeyFails. The assertion below is scoped to the ciphertext values
        // rather than the whole file because response.curl_info (e.g. local_port) is a fresh,
        // non-reproducible value on every real HTTP round-trip and has nothing to do with the
        // encryption feature's determinism guarantee.
        VCR::turnOn();
        VCR::insertCassette('deterministic-post-2nd');
        $this->post();
        VCR::turnOff();
        preg_match_all('/vcr:enc:v1:[A-Za-z0-9+\/=]+/', $this->cassetteContents('deterministic-post-2nd'), $second);

        $this->assertNotEmpty($first[0], 'Expected at least one encrypted field in the recorded cassette.');
        $this->assertSame($first[0], $second[0], 'A re-recorded cassette must produce byte-identical ciphertext for its encrypted fields.');
    }

    public function testReplayingWithTheWrongKeyFails(): void
    {
        $this->recordAndReplay('wrong-key-post', fn (): int => $this->post());

        // A distinct cassette name avoids VCRFactory's per-name Storage cache, which would
        // otherwise hand back the instance already bound to the correct key.
        file_put_contents($this->cassettePath('wrong-key-post-replay'), $this->cassetteContents('wrong-key-post'));

        VCR::configure()->setStorageFactory(
            EncryptedStorageFactory::withKey(
                new YamlStorageFactory(),
                EncryptionKey::fromBinary(str_repeat("\x7f", \SODIUM_CRYPTO_KDF_KEYBYTES))
            )
        );

        $this->expectException(DecryptionFailedException::class);

        VCR::turnOn();
        VCR::insertCassette('wrong-key-post-replay');
        $this->post();
    }

    public function testMovingCiphertextToAnotherFieldBreaksReplay(): void
    {
        $this->recordAndReplay('tampered-post', fn (): int => $this->post());

        $raw = $this->cassetteContents('tampered-post');
        $this->assertSame(1, preg_match('/(vcr:enc:v1:[A-Za-z0-9+\/=]+)/', $raw, $matches));
        $tampered = preg_replace('/(vcr:enc:v1:[A-Za-z0-9+\/=]+)/', $matches[1], $raw);

        // A distinct cassette name avoids VCRFactory's per-name Storage cache; otherwise the
        // tampered bytes below are never re-parsed, see testReplayingWithTheWrongKeyFails.
        file_put_contents($this->cassettePath('tampered-post-replay'), (string) $tampered);

        $this->expectException(DecryptionFailedException::class);

        VCR::turnOn();
        VCR::insertCassette('tampered-post-replay');
        $this->post();
    }

    public function testAPlaintextCassetteStaysReplayable(): void
    {
        VCR::configure()->setStorageFactory(new YamlStorageFactory());

        $this->recordAndReplay('plaintext-post', fn (): int => $this->post());

        // A distinct cassette name avoids VCRFactory's per-name Storage cache, which would
        // otherwise hand back the already-cached plain Yaml storage from the phase above, same
        // reasoning as testReplayingWithTheWrongKeyFails.
        file_put_contents($this->cassettePath('plaintext-post-replay'), $this->cassetteContents('plaintext-post'));

        VCR::configure()->setStorageFactory(
            EncryptedStorageFactory::withKey(new YamlStorageFactory(), $this->key)
        );

        $countBefore = $this->server()->getRequestCount();

        VCR::turnOn();
        VCR::insertCassette('plaintext-post-replay');
        $status = $this->post();
        VCR::turnOff();

        $this->assertSame($countBefore, $this->server()->getRequestCount(), 'Replay must not hit the server.');
        $this->assertSame(200, $status, 'Cassettes recorded before encryption must keep working.');
    }

    public function testRequestsWithoutSensitiveFieldsAreRecordedUnchanged(): void
    {
        $this->recordAndReplay('plain-get', fn (): int => $this->get());

        $raw = $this->cassetteContents('plain-get');

        $this->assertStringContainsString(self::SECRET, $raw, 'Query string secrets are a documented limitation.');
    }

    public function testACustomPolicyIsApplied(): void
    {
        VCR::configure()->setStorageFactory(
            EncryptedStorageFactory::withKey(
                new YamlStorageFactory(),
                $this->key,
                new EncryptionPolicy(['response.body'], [])
            )
        );

        $this->recordAndReplay('custom-policy-post', fn (): int => $this->post());

        $raw = $this->cassetteContents('custom-policy-post');

        $this->assertStringContainsString(self::SECRET, $raw, 'The request body is outside this policy.');
        $this->assertStringContainsString('vcr:enc:v1:', $raw, 'The response body is inside it.');
    }

    public function testWorksOverJsonStorageAsWell(): void
    {
        VCR::configure()->setStorageFactory(
            EncryptedStorageFactory::withKey(new JsonStorageFactory(), $this->key)
        );

        $this->recordAndReplay('encrypted-json-post', fn (): int => $this->post());

        $raw = $this->cassetteContents('encrypted-json-post');

        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertStringContainsString('vcr:enc:v1:', $raw);
    }

    public function testRecordModeAllPurgesAndReRecords(): void
    {
        $this->recordAndReplay('mode-all-post', fn (): int => $this->post());

        VCR::configure()->setMode(VCR::MODE_ALL);

        $countBefore = $this->server()->getRequestCount();
        VCR::turnOn();
        VCR::insertCassette('mode-all-post');
        $this->post();
        VCR::turnOff();

        $this->assertSame($countBefore + 1, $this->server()->getRequestCount(), 'MODE_ALL must re-record.');
        $this->assertStringNotContainsString(self::SECRET, $this->cassetteContents('mode-all-post'));
    }
}
