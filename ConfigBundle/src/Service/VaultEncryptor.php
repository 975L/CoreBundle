<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class VaultEncryptor
{
    private const string MARKER = 'C975L:';

    // GCM authenticates what it decrypts: a wrong key or an altered ciphertext fails on the tag, where CBC had nothing to fail on and handed back whatever the padding let through
    private const string ALGO = 'aes-256-gcm';

    // What every value stored before this was written with. Read here, never written again - c975l:config:encrypt-sensitive rewrites what it finds
    private const string LEGACY_ALGO = 'aes-256-cbc';

    // GCM's own nonce length, and the tag openssl appends. Both are stated because the payload is laid out on them: iv, then tag, then ciphertext
    private const int IV_LENGTH = 12;

    private const int TAG_LENGTH = 16;

    private const int LEGACY_IV_LENGTH = 16;

    public function __construct(
        #[Autowire(env: 'default::C975L_VAULT_KEY')]
        private readonly ?string $vaultKey,
    ) {
    }

    // Encrypts a plain-text value and returns the stored format C975L:<base64(iv+tag+ciphertext)>
    public function encrypt(string $plainValue): string
    {
        if ('' === $plainValue) {
            return '';
        }

        $key = $this->deriveKey();
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt($plainValue, self::ALGO, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);
        if (false === $ciphertext) {
            throw new \RuntimeException('Encryption failed.');
        }

        return self::MARKER . base64_encode($iv . $tag . $ciphertext);
    }

    // Decrypts a stored value, whichever of the two formats it was written in
    public function decrypt(string $encryptedValue): string
    {
        if ('' === $encryptedValue) {
            return '';
        }

        if (!$this->isEncrypted($encryptedValue)) {
            // Value not yet encrypted (pre-migration plaintext) — return as-is
            return $encryptedValue;
        }

        $raw = $this->rawPayload($encryptedValue);
        $key = $this->deriveKey();

        // The current format is tried first, and that order is the whole safety of holding both under one marker: a legacy payload cannot satisfy GCM's tag (2^-128), where a current one read as CBC would come back as garbage the padding lets through once in 256 - the very hole this format closes
        $plain = $this->decryptCurrent($raw, $key);

        if (false === $plain) {
            $plain = $this->decryptLegacy($raw, $key);
        }

        if (false === $plain) {
            // One failure, two causes worth telling apart: nothing read at all points at the key, where a legacy payload this very key did read and the UTF-8 guard below then rejected points at the value itself - a secret written from something other than text, which no key will ever bring back as one. Blaming the key there sends whoever reads the log looking in the wrong place
            if (false !== $this->decryptLegacyRaw($raw, $key)) {
                throw new \RuntimeException('Decryption failed: this legacy value did not come back as text. Either the key is wrong, or the value was not text to begin with - re-encrypt it from its source with "c975l:config:encrypt-sensitive".');
            }

            throw new \RuntimeException('Decryption failed. Check your C975L_VAULT_KEY.');
        }

        return $plain;
    }

    // Returns true if the value was produced by this encryptor
    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::MARKER);
    }

    // Whether a stored value still carries the format this class no longer writes - what c975l:config:encrypt-sensitive converts, and what tells it apart from a row already done, so a deployment stops writing once a site is converted. False for anything this key cannot read at all, which is not this method's business to report on
    public function isLegacyEncrypted(string $value): bool
    {
        if ('' === $value || !$this->isEncrypted($value) || !$this->isKeyDefined()) {
            return false;
        }

        $raw = $this->rawPayload($value);
        $key = $this->deriveKey();

        return false === $this->decryptCurrent($raw, $key) && false !== $this->decryptLegacy($raw, $key);
    }

    // Returns true if the vault key is defined
    public function isKeyDefined(): bool
    {
        return null !== $this->vaultKey && '' !== $this->vaultKey;
    }

    // The stored value with its marker off and its base64 undone, as the two formats below read it
    private function rawPayload(string $encryptedValue): string
    {
        return (string) base64_decode(substr($encryptedValue, strlen(self::MARKER)), true);
    }

    private function decryptCurrent(string $raw, string $key): string | false
    {
        if (strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return false;
        }

        return openssl_decrypt(
            substr($raw, self::IV_LENGTH + self::TAG_LENGTH),
            self::ALGO,
            $key,
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::IV_LENGTH),
            substr($raw, self::IV_LENGTH, self::TAG_LENGTH)
        );
    }

    // CBC authenticates nothing, so a wrong key is only ever caught by the padding openssl checks on its way out - and random bytes satisfy that padding once in 256, handing back garbage rather than false. A config value is text, always, so anything that is not is that garbage - measured over 200000 wrong-key decryptions, 805 passed the padding and not one was valid UTF-8. preg_match('//u') rather than mb_check_encoding(), PCRE being always there where ext-mbstring is declared nowhere in composer.json; the current format needs none of this, its tag having already answered
    private function decryptLegacy(string $raw, string $key): string | false
    {
        $plain = $this->decryptLegacyRaw($raw, $key);

        if (false === $plain || 1 !== preg_match('//u', $plain)) {
            return false;
        }

        return $plain;
    }

    // The same read with the text guard left off, which is what tells a key that read nothing from a value that came back as something other than text - see decrypt(). Nothing else may use it: what it returns is exactly what the guard above exists to refuse
    private function decryptLegacyRaw(string $raw, string $key): string | false
    {
        if (strlen($raw) <= self::LEGACY_IV_LENGTH) {
            return false;
        }

        return openssl_decrypt(
            substr($raw, self::LEGACY_IV_LENGTH),
            self::LEGACY_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            substr($raw, 0, self::LEGACY_IV_LENGTH)
        );
    }

    private function deriveKey(): string
    {
        if (!$this->isKeyDefined()) {
            throw new \RuntimeException('C975L_VAULT_KEY is not defined. Add it to your .env.local to handle sensitive settings.');
        }

        return hash('sha256', $this->vaultKey, true);
    }
}
