<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Encryption;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Encryption\DecryptionFailedException;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\Storage\Encryption\EncryptionPolicy;
use VCR\Storage\Encryption\SodiumCipher;

final class EncryptionPolicyTest extends TestCase
{
    private SodiumCipher $cipher;

    protected function setUp(): void
    {
        if (!\extension_loaded('sodium')) {
            $this->markTestSkipped('The encrypted storage requires ext-sodium, which is not loaded.');
        }

        $this->cipher = new SodiumCipher(
            EncryptionKey::fromBinary(str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES))
        );
    }

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

    public function testEncryptsTheDefaultBodyFields(): void
    {
        $encrypted = (new EncryptionPolicy())->encrypt($this->recording(), $this->cipher);

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['request']['body']);
        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['response']['body']);
    }

    public function testEncryptsTheDefaultHeaders(): void
    {
        $encrypted = (new EncryptionPolicy())->encrypt($this->recording(), $this->cipher);

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['request']['headers']['authorization']);
        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['response']['headers']['Set-Cookie']);
    }

    public function testLeavesRoutingFieldsReadable(): void
    {
        $encrypted = (new EncryptionPolicy())->encrypt($this->recording(), $this->cipher);

        $this->assertSame('POST', $encrypted['request']['method']);
        $this->assertSame('https://api.example.com/login', $encrypted['request']['url']);
        $this->assertSame(['code' => 200, 'message' => 'OK'], $encrypted['response']['status']);
        $this->assertSame(0, $encrypted['index']);
    }

    public function testLeavesHeadersOutsideTheListReadable(): void
    {
        $encrypted = (new EncryptionPolicy())->encrypt($this->recording(), $this->cipher);

        $this->assertSame('api.example.com', $encrypted['request']['headers']['Host']);
        $this->assertSame('abc-123', $encrypted['request']['headers']['X-Trace-Id']);
        $this->assertSame('application/json', $encrypted['response']['headers']['Content-Type']);
    }

    public function testHeaderNamesMatchCaseInsensitively(): void
    {
        $policy = new EncryptionPolicy([], ['AUTHORIZATION']);

        $encrypted = $policy->encrypt($this->recording(), $this->cipher);

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['request']['headers']['authorization']);
    }

    public function testRoundTripRestoresTheOriginalRecording(): void
    {
        $policy = new EncryptionPolicy();
        $original = $this->recording();

        $restored = $policy->decrypt($policy->encrypt($original, $this->cipher), $this->cipher);

        $this->assertSame($original, $restored);
    }

    public function testArrayFieldsAreStoredAsStringsAndRestoredAsArrays(): void
    {
        $policy = new EncryptionPolicy();

        $encrypted = $policy->encrypt($this->recording(), $this->cipher);
        $this->assertIsString($encrypted['request']['post_fields']);

        $restored = $policy->decrypt($encrypted, $this->cipher);
        $this->assertSame(['user' => 'alice', 'password' => 'hunter2'], $restored['request']['post_fields']);
    }

    public function testNestedArrayFieldsSurviveTheRoundTrip(): void
    {
        $policy = new EncryptionPolicy(['request.post_files'], []);
        $recording = [
            'request' => ['post_files' => [['fieldName' => 'file', 'contentType' => 'text/plain']]],
            'index' => 0,
        ];

        $restored = $policy->decrypt($policy->encrypt($recording, $this->cipher), $this->cipher);

        $this->assertSame($recording, $restored);
    }

    public function testMissingFieldsAreSkipped(): void
    {
        $recording = ['request' => ['method' => 'GET', 'url' => 'https://example.com'], 'index' => 0];

        $encrypted = (new EncryptionPolicy())->encrypt($recording, $this->cipher);

        $this->assertSame($recording, $encrypted);
    }

    public function testRecordingWithoutHeadersIsHandled(): void
    {
        $recording = ['request' => ['method' => 'GET', 'url' => 'https://example.com', 'body' => 'x'], 'index' => 0];

        $encrypted = (new EncryptionPolicy())->encrypt($recording, $this->cipher);

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['request']['body']);
    }

    public function testNonArrayHeadersAreIgnored(): void
    {
        $recording = ['request' => ['headers' => 'not-an-array', 'body' => 'x'], 'index' => 0];

        $encrypted = (new EncryptionPolicy())->encrypt($recording, $this->cipher);

        $this->assertSame('not-an-array', $encrypted['request']['headers']);
    }

    public function testPlaintextFieldsArePassedThroughOnDecrypt(): void
    {
        $recording = $this->recording();

        $restored = (new EncryptionPolicy())->decrypt($recording, $this->cipher);

        $this->assertSame($recording, $restored, 'A cassette recorded without encryption must stay readable.');
    }

    public function testPartiallyEncryptedRecordingsAreHandled(): void
    {
        $policy = new EncryptionPolicy();
        $recording = $this->recording();
        $recording['request']['body'] = $this->cipher->encrypt('s:{"password":"hunter2"}', 'request.body');

        $restored = $policy->decrypt($recording, $this->cipher);

        $this->assertSame('{"password":"hunter2"}', $restored['request']['body']);
        $this->assertSame('welcome', $restored['response']['body']);
    }

    public function testCustomFieldPathsReplaceTheDefaults(): void
    {
        $policy = new EncryptionPolicy(['response.body'], []);

        $encrypted = $policy->encrypt($this->recording(), $this->cipher);

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['response']['body']);
        $this->assertSame('{"password":"hunter2"}', $encrypted['request']['body']);
    }

    public function testEmptyListsEncryptNothing(): void
    {
        $recording = $this->recording();

        $this->assertSame($recording, (new EncryptionPolicy([], []))->encrypt($recording, $this->cipher));
    }

    public function testDefaultsAreExposedAsConstants(): void
    {
        $this->assertContains('request.body', EncryptionPolicy::DEFAULT_FIELD_PATHS);
        $this->assertContains('request.post_fields', EncryptionPolicy::DEFAULT_FIELD_PATHS);
        $this->assertContains('request.post_files', EncryptionPolicy::DEFAULT_FIELD_PATHS);
        $this->assertContains('response.body', EncryptionPolicy::DEFAULT_FIELD_PATHS);
        $this->assertContains('Authorization', EncryptionPolicy::DEFAULT_HEADER_NAMES);
        $this->assertContains('Set-Cookie', EncryptionPolicy::DEFAULT_HEADER_NAMES);
    }

    public function testEachFieldIsEncryptedUnderItsOwnPath(): void
    {
        $recording = ['request' => ['body' => 'same'], 'response' => ['body' => 'same'], 'index' => 0];

        $encrypted = (new EncryptionPolicy())->encrypt($recording, $this->cipher);

        $this->assertNotSame(
            $encrypted['request']['body'],
            $encrypted['response']['body'],
            'Identical values in different fields must not produce identical ciphertext.'
        );
    }

    public function testAnUnknownTypeTagIsRejected(): void
    {
        $recording = [
            'request' => ['body' => $this->cipher->encrypt('x:garbage', 'request.body')],
            'index' => 0,
        ];

        $this->expectException(DecryptionFailedException::class);

        (new EncryptionPolicy())->decrypt($recording, $this->cipher);
    }

    public function testDecryptingWithTheWrongKeyFails(): void
    {
        $policy = new EncryptionPolicy();
        $encrypted = $policy->encrypt($this->recording(), $this->cipher);
        $other = new SodiumCipher(EncryptionKey::fromBinary(str_repeat("\x7f", \SODIUM_CRYPTO_KDF_KEYBYTES)));

        $this->expectException(DecryptionFailedException::class);

        $policy->decrypt($encrypted, $other);
    }

    public function testEncryptsTheOutboundCurlRequestHeaderTrace(): void
    {
        $recording = $this->recording();
        $recording['response']['curl_info'] = [
            'request_header' => "GET / HTTP/1.1\r\nAuthorization: Bearer secret\r\n\r\n",
        ];

        $encrypted = (new EncryptionPolicy())->encrypt($recording, $this->cipher);

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['response']['curl_info']['request_header']);
        $this->assertStringNotContainsString('Bearer secret', $encrypted['response']['curl_info']['request_header']);

        $restored = (new EncryptionPolicy())->decrypt($encrypted, $this->cipher);

        $this->assertSame(
            "GET / HTTP/1.1\r\nAuthorization: Bearer secret\r\n\r\n",
            $restored['response']['curl_info']['request_header']
        );
    }

    public function testHeaderNamesContainingDotsAreHandledCorrectly(): void
    {
        $policy = new EncryptionPolicy([], ['X.Api.Secret']);
        $recording = $this->recording();
        $recording['request']['headers']['X.Api.Secret'] = 'top-secret';

        $encrypted = $policy->encrypt($recording, $this->cipher);

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted['request']['headers']['X.Api.Secret']);

        $restored = $policy->decrypt($encrypted, $this->cipher);

        $this->assertSame('top-secret', $restored['request']['headers']['X.Api.Secret']);
    }
}
