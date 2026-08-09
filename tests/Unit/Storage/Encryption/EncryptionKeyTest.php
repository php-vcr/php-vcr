<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage\Encryption;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Encryption\EncryptionKey;
use VCR\VCRException;

final class EncryptionKeyTest extends TestCase
{
    public function testGenerateProducesSubkeysOfTheExpectedLength(): void
    {
        $key = EncryptionKey::generate();

        $this->assertSame(\SODIUM_CRYPTO_KDF_KEYBYTES, \strlen($key->encryptionKey()));
        $this->assertSame(\SODIUM_CRYPTO_KDF_KEYBYTES, \strlen($key->nonceKey()));
    }

    public function testGenerateProducesADifferentKeyEachTime(): void
    {
        $this->assertNotSame(
            EncryptionKey::generate()->toBase64(),
            EncryptionKey::generate()->toBase64()
        );
    }

    public function testEncryptionAndNonceSubkeysDiffer(): void
    {
        $key = EncryptionKey::generate();

        $this->assertNotSame(
            $key->encryptionKey(),
            $key->nonceKey(),
            'Deriving the nonce with the encryption key would reuse one key in two roles.'
        );
    }

    public function testSubkeyDerivationIsDeterministic(): void
    {
        $master = str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES);

        $this->assertSame(
            EncryptionKey::fromBinary($master)->encryptionKey(),
            EncryptionKey::fromBinary($master)->encryptionKey()
        );
        $this->assertSame(
            EncryptionKey::fromBinary($master)->nonceKey(),
            EncryptionKey::fromBinary($master)->nonceKey()
        );
    }

    public function testDifferentMasterKeysProduceDifferentSubkeys(): void
    {
        $first = EncryptionKey::fromBinary(str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES));
        $second = EncryptionKey::fromBinary(str_repeat("\x7f", \SODIUM_CRYPTO_KDF_KEYBYTES));

        $this->assertNotSame($first->encryptionKey(), $second->encryptionKey());
    }

    public function testBase64RoundTripPreservesTheSubkeys(): void
    {
        $key = EncryptionKey::generate();

        $restored = EncryptionKey::fromBase64($key->toBase64());

        $this->assertSame($key->encryptionKey(), $restored->encryptionKey());
        $this->assertSame($key->nonceKey(), $restored->nonceKey());
    }

    public function testAcceptsAMasterKeyContainingNullBytes(): void
    {
        $master = str_repeat("\x00", \SODIUM_CRYPTO_KDF_KEYBYTES);

        $this->assertSame(
            \SODIUM_CRYPTO_KDF_KEYBYTES,
            \strlen(EncryptionKey::fromBinary($master)->encryptionKey())
        );
    }

    public function testFromBinaryRejectsAKeyThatIsTooShort(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage('must be exactly 32 bytes');

        EncryptionKey::fromBinary('too-short');
    }

    public function testFromBinaryRejectsAKeyThatIsTooLong(): void
    {
        $this->expectException(VCRException::class);

        EncryptionKey::fromBinary(str_repeat("\x2a", \SODIUM_CRYPTO_KDF_KEYBYTES + 1));
    }

    public function testFromBase64RejectsInputThatIsNotBase64(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage('valid base64');

        EncryptionKey::fromBase64('not valid base64 !!');
    }

    public function testFromBase64RejectsValidBase64OfTheWrongLength(): void
    {
        $this->expectException(VCRException::class);
        $this->expectExceptionMessage('must be exactly 32 bytes');

        EncryptionKey::fromBase64(base64_encode('short'));
    }
}
