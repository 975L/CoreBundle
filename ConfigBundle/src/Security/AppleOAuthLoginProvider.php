<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Model\OAuthIdentity;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Psr\Log\LoggerInterface;

// "Sign in with Apple", required of an iPhone app offering Google's (App Review Guideline 4.8): its answer is posted back (see OAuthLoginController::checkPosted()), the address read from the id_token, and the client secret a token the site signs itself with the "login-apple-*" configs
class AppleOAuthLoginProvider implements OAuthLoginProviderInterface
{
    use OAuthCredentialTrait;

    public const string AUTHORIZATION_ENDPOINT = 'https://appleid.apple.com/auth/authorize';

    public const string TOKEN_ENDPOINT = 'https://appleid.apple.com/auth/token';

    // The issuer of the id_token, and the audience of the client secret
    public const string ISSUER = 'https://appleid.apple.com';

    // "email" alone: the address is all a login needs, Apple never shares the name again after the first sign-in anyway
    public const string SCOPE = 'email';

    // How long a signed client secret is good for: one exchange is all it serves, Apple accepting up to six months
    private const int SECRET_LIFETIME = 300;

    // The logger is optional: an app without Monolog leaves it null and everything else works the same, only the refusals go unrecorded
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function getKey(): string
    {
        return 'apple';
    }

    public function getName(): string
    {
        return 'Apple';
    }

    public function getAuthorizationEndpoint(): string
    {
        return self::AUTHORIZATION_ENDPOINT;
    }

    // Apple posts its answer as soon as a scope is asked for, and says so only if told to
    public function getAuthorizationParameters(): array
    {
        return ['response_mode' => 'form_post'];
    }

    public function getTokenEndpoint(): string
    {
        return self::TOKEN_ENDPOINT;
    }

    public function getScope(): string
    {
        return self::SCOPE;
    }

    // The Services ID made for the site's web sign-in (fr.example.login, say), not the app's bundle identifier
    public function getClientId(): ?string
    {
        return $this->credential('login-apple-services-id');
    }

    // A JWT signed with the site's Apple key (ES256), made afresh for each exchange: null while any of the four values is missing, or when the key can't be read
    public function getClientSecret(): ?string
    {
        $servicesId = $this->getClientId();
        $teamId = $this->credential('login-apple-team-id');
        $keyId = $this->credential('login-apple-key-id');
        $privateKey = $this->privateKey();

        if (null === $servicesId || null === $teamId || null === $keyId || null === $privateKey) {
            return null;
        }

        $now = time();
        $unsigned = self::base64Url((string) json_encode(['alg' => 'ES256', 'kid' => $keyId])) . '.' . self::base64Url((string) json_encode([
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + self::SECRET_LIFETIME,
            'aud' => self::ISSUER,
            'sub' => $servicesId,
        ]));

        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $this->logger?->warning('The Apple key could not sign the client secret.');

            return null;
        }

        $raw = self::rawSignature($signature);

        return null === $raw ? null : $unsigned . '.' . self::base64Url($raw);
    }

    // The id_token came straight from Apple's token endpoint, over TLS and against the site's own signed secret: what it says is Apple's, and checking who issued it, for whom and until when is enough without fetching Apple's keys (OpenID Connect Core, 3.1.3.7). A hidden address (…@privaterelay.appleid.com) is verified like any other
    public function fetchIdentity(array $token): ?OAuthIdentity
    {
        $claims = self::claims($token['id_token'] ?? null);

        if (null === $claims || !$this->isMeantForThisSite($claims) || !is_string($claims['email'] ?? null)) {
            $this->logger?->warning('Apple returned no usable id_token, the login can only be refused.');

            return null;
        }

        // Apple has written it as a string and as a boolean over the years: anything else than a clear yes counts as no
        return new OAuthIdentity(
            $claims['email'],
            true === filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }

    // What the id_token says, its signature left unread (see fetchIdentity())
    /** @return array<string, mixed>|null */
    private static function claims(mixed $idToken): ?array
    {
        $parts = is_string($idToken) ? explode('.', $idToken) : [];
        $claims = 3 === count($parts) ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true) : null;

        return is_array($claims) ? $claims : null;
    }

    // Issued by Apple, for this site's Services ID, and not yet expired
    private function isMeantForThisSite(array $claims): bool
    {
        return self::ISSUER === ($claims['iss'] ?? null)
            && $this->getClientId() === ($claims['aud'] ?? null)
            && is_int($claims['exp'] ?? null) && $claims['exp'] >= time();
    }

    // The .p8 key as pasted, its line breaks lost or not on the way through a form: rebuilt from its base64 body alone
    private function privateKey(): ?\OpenSSLAsymmetricKey
    {
        $pasted = $this->credential('login-apple-private-key');
        if (null === $pasted) {
            return null;
        }

        $body = preg_replace('/-----[A-Z ]+-----|\s+/', '', $pasted);
        $key = openssl_pkey_get_private("-----BEGIN PRIVATE KEY-----\n" . chunk_split((string) $body, 64, "\n") . "-----END PRIVATE KEY-----\n");

        if (false === $key) {
            $this->logger?->warning('The Apple private key (login-apple-private-key) could not be read.');

            return null;
        }

        return $key;
    }

    // OpenSSL signs in DER (an ASN.1 sequence of two integers, under 128 bytes for P-256, so with a one-byte length), a JWT wants the two of them side by side, 32 bytes each
    private static function rawSignature(string $der): ?string
    {
        if ("\x30" !== ($der[0] ?? '')) {
            return null;
        }

        $offset = 2;
        $raw = '';
        for ($index = 0; $index < 2; ++$index) {
            if ("\x02" !== ($der[$offset] ?? '')) {
                return null;
            }
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\x00");
            $raw .= str_pad($integer, 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return 64 === strlen($raw) ? $raw : null;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
