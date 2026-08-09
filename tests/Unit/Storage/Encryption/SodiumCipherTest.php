<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Encryption;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Encryption\CipherInterface;
use VCR\Storage\Encryption\DecryptionFailedException;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\Storage\Encryption\SodiumCipher;
use VCR\Storage\Encryption\UnsupportedSchemeException;

final class SodiumCipherTest extends TestCase
{
    private EncryptionKey $key;

    private SodiumCipher $cipher;

    protected function setUp(): void
    {
        $this->key = EncryptionKey::fromBinary(str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES));
        $this->cipher = new SodiumCipher($this->key);
    }

    public function testImplementsCipherInterface(): void
    {
        $this->assertInstanceOf(CipherInterface::class, $this->cipher);
    }

    public function testRoundTripReturnsThePlaintext(): void
    {
        $encrypted = $this->cipher->encrypt('Bearer secret', 'request.headers.Authorization');

        $this->assertSame(
            'Bearer secret',
            $this->cipher->decrypt($encrypted, 'request.headers.Authorization')
        );
    }

    public function testCiphertextCarriesTheSchemeMarker(): void
    {
        $encrypted = $this->cipher->encrypt('Bearer secret', 'request.headers.Authorization');

        $this->assertStringStartsWith('vcr:enc:v1:', $encrypted);
        $this->assertStringNotContainsString('Bearer secret', $encrypted);
    }

    public function testEncryptionIsDeterministic(): void
    {
        $first = $this->cipher->encrypt('Bearer secret', 'request.headers.Authorization');
        $second = $this->cipher->encrypt('Bearer secret', 'request.headers.Authorization');

        $this->assertSame($first, $second, 'A re-recorded cassette must stay byte-identical.');
    }

    public function testDifferentPlaintextsProduceDifferentCiphertext(): void
    {
        $this->assertNotSame(
            $this->cipher->encrypt('one', 'request.body'),
            $this->cipher->encrypt('two', 'request.body')
        );
    }

    public function testSameValueInDifferentFieldsYieldsDifferentCiphertext(): void
    {
        $header = $this->cipher->encrypt('secret', 'request.headers.Authorization');
        $body = $this->cipher->encrypt('secret', 'request.body');

        $this->assertNotSame($header, $body, 'The field path must feed the nonce derivation.');
    }

    public function testDecryptingUnderADifferentFieldPathFails(): void
    {
        $encrypted = $this->cipher->encrypt('Bearer secret', 'request.headers.Authorization');

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('request.body');

        $this->cipher->decrypt($encrypted, 'request.body');
    }

    public function testDecryptingTamperedCiphertextFails(): void
    {
        $encrypted = $this->cipher->encrypt('Bearer secret', 'request.body');
        $tampered = substr($encrypted, 0, -4).'AAAA';

        $this->expectException(DecryptionFailedException::class);

        $this->cipher->decrypt($tampered, 'request.body');
    }

    public function testDecryptingWithADifferentKeyFails(): void
    {
        $encrypted = $this->cipher->encrypt('Bearer secret', 'request.body');
        $other = new SodiumCipher(EncryptionKey::fromBinary(str_repeat("\x7f", \SODIUM_CRYPTO_KDF_KEYBYTES)));

        $this->expectException(DecryptionFailedException::class);

        $other->decrypt($encrypted, 'request.body');
    }

    public function testMissingSchemeSeparatorIsReportedAsMalformed(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('malformed');

        $this->cipher->decrypt('vcr:enc:no-separator-here', 'request.body');
    }

    public function testPayloadShorterThanTheNonceIsReportedAsMalformed(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('malformed');

        $this->cipher->decrypt('vcr:enc:v1:'.base64_encode('tiny'), 'request.body');
    }

    public function testPayloadThatIsNotBase64IsReportedAsMalformed(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('malformed');

        $this->cipher->decrypt('vcr:enc:v1:not valid base64 !!', 'request.body');
    }

    public function testUnknownSchemeVersionIsReportedSeparately(): void
    {
        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('v99');

        $this->cipher->decrypt('vcr:enc:v99:'.base64_encode('whatever'), 'request.body');
    }

    public function testExceptionMessagesLeakNeitherPlaintextNorKey(): void
    {
        $encrypted = $this->cipher->encrypt('hunter2', 'request.body');

        try {
            $this->cipher->decrypt($encrypted, 'response.body');
            $this->fail('Expected a DecryptionFailedException.');
        } catch (DecryptionFailedException $exception) {
            $this->assertStringNotContainsString('hunter2', $exception->getMessage());
            $this->assertStringNotContainsString($this->key->toBase64(), $exception->getMessage());
        }
    }

    public function testIsEncryptedDetectsTheMarker(): void
    {
        $this->assertTrue($this->cipher->isEncrypted('vcr:enc:v1:abc'));
        $this->assertTrue($this->cipher->isEncrypted('vcr:enc:v99:abc'));
        $this->assertFalse($this->cipher->isEncrypted('Bearer secret'));
        $this->assertFalse($this->cipher->isEncrypted(''));
    }

    public function testBinaryPlaintextSurvivesTheRoundTrip(): void
    {
        $binary = random_bytes(64);

        $this->assertSame(
            $binary,
            $this->cipher->decrypt($this->cipher->encrypt($binary, 'response.body'), 'response.body')
        );
    }

    public function testEmptyPlaintextSurvivesTheRoundTrip(): void
    {
        $this->assertSame('', $this->cipher->decrypt($this->cipher->encrypt('', 'request.body'), 'request.body'));
    }

    public function testLargePlaintextSurvivesTheRoundTrip(): void
    {
        $large = str_repeat('a', 1024 * 256);

        $this->assertSame(
            $large,
            $this->cipher->decrypt($this->cipher->encrypt($large, 'response.body'), 'response.body')
        );
    }
}
