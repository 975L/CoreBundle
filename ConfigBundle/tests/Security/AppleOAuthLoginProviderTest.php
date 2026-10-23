<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\Security\AppleOAuthLoginProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class AppleOAuthLoginProviderTest extends TestCase
{
    private const string SERVICES_ID = 'com.example.login';

    /**
     * A key made for the test, of the kind Apple hands out: an EC key on P-256, exported as the .p8 file holds it.
     *
     * @return array{0: string, 1: \OpenSSLAsymmetricKey}
     */
    private function appleKey(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $pem);

        return [$pem, $key];
    }

    /**
     * @param array<string, string> $configs
     */
    private function createProvider(array $configs): AppleOAuthLoginProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): ?string => $configs[$slug] ?? null);

        return new AppleOAuthLoginProvider($configService);
    }

    /**
     * @return array<string, string>
     */
    private function credentials(string $pem): array
    {
        return [
            'login-apple-services-id' => self::SERVICES_ID,
            'login-apple-team-id' => 'TEAM123456',
            'login-apple-key-id' => 'KEY1234567',
            'login-apple-private-key' => $pem,
        ];
    }

    private static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }

    /**
     * An id_token as Apple's token endpoint answers it, its signature left out: nothing reads it (see fetchIdentity()).
     *
     * @param array<string, mixed> $claims
     */
    private static function idToken(array $claims): string
    {
        $encode = static fn (array $part): string => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');

        return $encode(['alg' => 'RS256', 'kid' => 'apple']) . '.' . $encode($claims) . '.signature';
    }

    /**
     * @return array<string, mixed>
     */
    private static function claims(array $overrides = []): array
    {
        return $overrides + [
            'iss' => 'https://appleid.apple.com',
            'aud' => self::SERVICES_ID,
            'exp' => time() + 600,
            'sub' => '001234.abcdef',
            'email' => 'parent@privaterelay.appleid.com',
            'email_verified' => 'true',
        ];
    }

    public function testItAsksForTheAddressAloneAndForItsAnswerToBePosted(): void
    {
        $provider = $this->createProvider([]);

        $this->assertSame('apple', $provider->getKey());
        $this->assertSame('email', $provider->getScope());
        $this->assertSame(['response_mode' => 'form_post'], $provider->getAuthorizationParameters());
    }

    // The button only shows once all four values are there: a secret can't be signed without any of them
    public function testNoSecretWhileAnyValueIsMissing(): void
    {
        [$pem] = $this->appleKey();

        foreach (array_keys($this->credentials($pem)) as $missing) {
            $configs = $this->credentials($pem);
            unset($configs[$missing]);

            $this->assertNull($this->createProvider($configs)->getClientSecret(), $missing);
        }
        $this->assertNull($this->createProvider(['login-apple-private-key' => 'not a key'] + $this->credentials($pem))->getClientSecret());
    }

    // What Apple checks: its team as issuer, the Services ID as subject, its own address as audience, a short life, the key named in the header - and an ES256 signature in the raw 64-byte form a JWT wants, not OpenSSL's DER
    public function testTheClientSecretIsAJwtSignedWithTheSitesKey(): void
    {
        [$pem, $key] = $this->appleKey();

        $secret = $this->createProvider($this->credentials($pem))->getClientSecret();

        $this->assertIsString($secret);
        [$header, $payload, $signature] = explode('.', $secret);
        $this->assertSame(['alg' => 'ES256', 'kid' => 'KEY1234567'], json_decode(self::base64UrlDecode($header), true));
        $claims = json_decode(self::base64UrlDecode($payload), true);
        $this->assertSame('TEAM123456', $claims['iss']);
        $this->assertSame(self::SERVICES_ID, $claims['sub']);
        $this->assertSame('https://appleid.apple.com', $claims['aud']);
        $this->assertLessThanOrEqual(300, $claims['exp'] - $claims['iat']);

        $raw = self::base64UrlDecode($signature);
        $this->assertSame(64, strlen($raw));
        // Back to DER, the one form openssl_verify() reads, each integer given a leading zero byte when its high bit is set
        $integer = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            if ('' === $bytes || ord($bytes[0]) > 0x7F) {
                $bytes = "\x00" . $bytes;
            }

            return "\x02" . chr(strlen($bytes)) . $bytes;
        };
        $sequence = $integer(substr($raw, 0, 32)) . $integer(substr($raw, 32));
        $details = openssl_pkey_get_details($key);
        $this->assertSame(1, openssl_verify($header . '.' . $payload, "\x30" . chr(strlen($sequence)) . $sequence, $details['key'], OPENSSL_ALGO_SHA256));
    }

    // A .p8 pasted into a form may lose its line breaks, or come with spaces: read all the same
    public function testAKeyPastedWithoutItsLineBreaksIsStillRead(): void
    {
        [$pem] = $this->appleKey();

        $flattened = '  ' . str_replace("\n", ' ', $pem) . '  ';

        $this->assertIsString($this->createProvider(['login-apple-private-key' => $flattened] + $this->credentials($pem))->getClientSecret());
    }

    public function testTheIdTokenGivesAVerifiedAddressEvenAHiddenOne(): void
    {
        $identity = $this->createProvider($this->credentials('-'))->fetchIdentity(['id_token' => self::idToken(self::claims())]);

        $this->assertSame('parent@privaterelay.appleid.com', $identity?->email);
        $this->assertTrue($identity->emailVerified);

        // Apple has also written it as a real boolean, and anything else than a clear yes counts as no
        $this->assertTrue($this->createProvider($this->credentials('-'))->fetchIdentity(['id_token' => self::idToken(self::claims(['email_verified' => true]))])?->emailVerified);
        $this->assertFalse($this->createProvider($this->credentials('-'))->fetchIdentity(['id_token' => self::idToken(self::claims(['email_verified' => 'false']))])?->emailVerified);
    }

    // Issued by someone else, for another site, or expired: refused rather than trusted
    public function testAnIdTokenNotMeantForThisSiteIsNoIdentity(): void
    {
        $provider = $this->createProvider($this->credentials('-'));

        $this->assertNull($provider->fetchIdentity(['id_token' => self::idToken(self::claims(['iss' => 'https://evil.test']))]));
        $this->assertNull($provider->fetchIdentity(['id_token' => self::idToken(self::claims(['aud' => 'com.other.login']))]));
        $this->assertNull($provider->fetchIdentity(['id_token' => self::idToken(self::claims(['exp' => time() - 1]))]));
    }

    public function testAnAnswerWithoutAUsableIdTokenIsNoIdentity(): void
    {
        $provider = $this->createProvider($this->credentials('-'));

        $this->assertNull($provider->fetchIdentity(['access_token' => 'an-access-token']));
        $this->assertNull($provider->fetchIdentity(['id_token' => 'not-a-jwt']));
        $claims = self::claims();
        unset($claims['email']);
        $this->assertNull($provider->fetchIdentity(['id_token' => self::idToken($claims)]));
    }
}
