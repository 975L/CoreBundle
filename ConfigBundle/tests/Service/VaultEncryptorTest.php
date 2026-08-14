<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\VaultEncryptor;
use PHPUnit\Framework\TestCase;

class VaultEncryptorTest extends TestCase
{
    public function testEncryptThenDecryptRoundTripsToTheOriginalPlainValue(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $encrypted = $encryptor->encrypt('secret-api-key');

        $this->assertNotSame('secret-api-key', $encrypted);
        $this->assertSame('secret-api-key', $encryptor->decrypt($encrypted));
    }

    public function testEncryptPrefixesTheStoredValueWithTheMarker(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $this->assertTrue($encryptor->isEncrypted($encryptor->encrypt('value')));
        $this->assertFalse($encryptor->isEncrypted('plain-value'));
    }

    // Two encryptions of the same value must differ (random IV), yet both decrypt back correctly
    public function testEncryptUsesARandomIvSoTwoEncryptionsOfTheSameValueDiffer(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $first = $encryptor->encrypt('same-value');
        $second = $encryptor->encrypt('same-value');

        $this->assertNotSame($first, $second);
        $this->assertSame('same-value', $encryptor->decrypt($first));
        $this->assertSame('same-value', $encryptor->decrypt($second));
    }

    public function testEncryptAndDecryptReturnEmptyStringForEmptyInput(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $this->assertSame('', $encryptor->encrypt(''));
        $this->assertSame('', $encryptor->decrypt(''));
    }

    // Values stored before the vault key was introduced (plain text) are returned unchanged
    public function testDecryptReturnsUnrecognizedValueAsIsWhenNotMarked(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $this->assertSame('pre-migration-plaintext', $encryptor->decrypt('pre-migration-plaintext'));
    }

    // The value below is "sk_live_secret_value" encrypted with "another-key", picked among 158 tries for the one property that makes it a fixture: decrypted with the key under test, it satisfies the PKCS#7 padding openssl checks, so CBC hands back garbage instead of failing
    private const string PAYLOAD_PASSING_THE_PADDING_UNDER_ANOTHER_KEY = 'C975L:Kj3nknnmY7XPzEAnTH+843u/nRSGns9AxYPdINABf/TibAvRqW65YRocirDLhIKH';

    // Two values as the aes-256-cbc this class no longer writes stored them, under the key every test here uses. Captured from that version rather than rebuilt, so what they prove is that a site's existing rows keep opening, not that two implementations agree
    private const string LEGACY_PAYLOAD = 'C975L:2w9FrnRq99FtTi9eKuCCLOZ6aWWRiCK0FVqd2zyk9WYR9o7sgHCO8yp+tFVZ1YNv';

    private const string LEGACY_PAYLOAD_PLAIN = 'sk_live_secret_value';

    private const string LEGACY_PAYLOAD_OUTSIDE_ASCII = 'C975L:deSOb9EJhJ0IscH/WlDc+3r5uJHXjGWsn+GNQBbZ3Q/Yv6yFgGI28BupeUqePXQw';

    private const string LEGACY_PAYLOAD_OUTSIDE_ASCII_PLAIN = "Clé d'API avec accents €";

    // The one case CBC cannot report on its own: nothing authenticates the ciphertext, so a wrong key is caught by the padding alone, which random bytes pass once in 256. Left there, those bytes are written over the encrypted value as the setting itself and its sensitive flag falls with them - a secret lost to a restored backup or a recreated .env.local. Being text is what a config value can be held to instead, and garbage never is
    public function testDecryptWithAWrongKeyThrowsEvenWhenThePaddingHappensToPass(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Decryption failed');

        $encryptor->decrypt(self::PAYLOAD_PASSING_THE_PADDING_UNDER_ANOTHER_KEY);
    }

    // The same guard, the other cause: a legacy value whose plain text never was text - a binary key, a string written in latin-1 - is refused under the very key that wrote it, and no key will ever bring it back. Told apart from a key mismatch, or its owner rotates a key that was right all along
    public function testDecryptTellsALegacyValueThatIsNotTextApartFromAWrongKey(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt("\xff\xfe\x00binary", 'aes-256-cbc', hash('sha256', 'a-test-vault-key', true), \OPENSSL_RAW_DATA, $iv);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('did not come back as text');

        $encryptor->decrypt('C975L:' . base64_encode($iv . $ciphertext));
    }

    // The check above reads the plain text, so it has to let through everything a setting legitimately holds - an accented label, a currency sign, an emoji - and not just ascii
    public function testDecryptRoundTripsAValueOutsideAscii(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        foreach (['Clé d\'API', 'Prix : 12 €', 'Signé 🔐', "Deux\nlignes"] as $value) {
            $this->assertSame($value, $encryptor->decrypt($encryptor->encrypt($value)));
        }
    }

    // The rows every site already holds: they open under the same key, and nothing had to be done to them first
    public function testDecryptStillReadsAValueWrittenInTheLegacyFormat(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $this->assertSame(self::LEGACY_PAYLOAD_PLAIN, $encryptor->decrypt(self::LEGACY_PAYLOAD));
        $this->assertSame(self::LEGACY_PAYLOAD_OUTSIDE_ASCII_PLAIN, $encryptor->decrypt(self::LEGACY_PAYLOAD_OUTSIDE_ASCII));
    }

    // Both formats live under one marker, so what tells them apart is the reading itself - which is what the conversion command asks before rewriting a row, and what stops it rewriting the same rows at every deployment
    public function testIsLegacyEncryptedTellsTheTwoFormatsApart(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $this->assertTrue($encryptor->isLegacyEncrypted(self::LEGACY_PAYLOAD));
        $this->assertFalse($encryptor->isLegacyEncrypted($encryptor->encrypt(self::LEGACY_PAYLOAD_PLAIN)));
    }

    // A converted value is the same secret in the current format, not a re-keyed one: the key never moves, only the algorithm holding it does
    public function testConvertingALegacyValueKeepsTheSecretAndLeavesTheLegacyFormat(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');

        $converted = $encryptor->encrypt($encryptor->decrypt(self::LEGACY_PAYLOAD));

        $this->assertStringStartsWith('C975L:', $converted);
        $this->assertSame(self::LEGACY_PAYLOAD_PLAIN, $encryptor->decrypt($converted));
        $this->assertFalse($encryptor->isLegacyEncrypted($converted));
    }

    // What the legacy format could not do at all: a ciphertext altered in the database is caught by the tag, where CBC would have handed back whatever those bytes decrypted to
    public function testDecryptRejectsAnAlteredCiphertext(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');
        $raw = base64_decode(substr($encryptor->encrypt('sk_live_secret_value'), 6), true);
        $raw[30] = "\0" === $raw[30] ? "\1" : "\0";

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Decryption failed');

        $encryptor->decrypt('C975L:' . base64_encode($raw));
    }

    // A malformed payload (a full 16-byte IV followed by ciphertext that isn't a multiple of the AES block size) makes openssl_decrypt() fail deterministically and without a PHP warning - the wrong-key case above needs a fixture picked for it instead
    public function testDecryptWithMalformedPayloadThrowsRuntimeException(): void
    {
        $encryptor = new VaultEncryptor('a-test-vault-key');
        $malformed = 'C975L:' . base64_encode(str_repeat("\0", 16) . 'short');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('Decryption failed');

        $encryptor->decrypt($malformed);
    }

    public function testEncryptWithoutVaultKeyThrowsRuntimeException(): void
    {
        $encryptor = new VaultEncryptor(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('C975L_VAULT_KEY is not defined');

        $encryptor->encrypt('value');
    }

    public function testIsKeyDefinedReflectsWhetherAVaultKeyWasProvided(): void
    {
        $this->assertTrue(new VaultEncryptor('a-key')->isKeyDefined());
        $this->assertFalse(new VaultEncryptor(null)->isKeyDefined());
        $this->assertFalse(new VaultEncryptor('')->isKeyDefined());
    }
}
