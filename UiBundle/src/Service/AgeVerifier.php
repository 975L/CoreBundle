<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// Age verification through go.cam (double anonymity, Arcom framework): the site never learns who the visitor is, only that go.cam found them of age. Their protocol is recoded here rather than their SDK vendored, the hosted one shipping with no licence: a request is encrypted with the partner's cipher key and signed with its hmac key, and so is the result coming back, which is the only thing trusted - never the "isPayloadValid" go.cam's own script answers in the browser
class AgeVerifier
{
    public const int ADULT_AGE = 18;

    public const string COOKIE_NAME = 'ui_age_verified';

    private const string IFRAME_URL = 'https://go.cam/%s/token/iframeCheck?p=%s&d=%s';

    private const array LANGUAGES = ['en', 'fr', 'de', 'nl', 'it', 'pt', 'es'];

    private const string SESSION_NONCE = 'ui_age_verification_nonce';

    private const string SESSION_VERIFIED = 'ui_age_verified';

    private const string REQUEST_URL = '_ui_age_verification_url';

    // As long as go.cam keeps its own verification in its cookie
    private const int COOKIE_LIFETIME = 30 * 86400;

    // Counted from the page drawn, go.cam's frame loading with it: a visitor reading for a while before the click still gets an answer, the single-use nonce being what stops a replay
    private const int RESULT_MAX_AGE = 3600;

    private const int STATE_SUCCESS = 2;

    private const string CIPHER = 'aes-256-cbc';

    private const int HMAC_SIZE = 32;

    // go.cam's numbered keys, which their SDK names KEY_ACCOUNT_DATA, KEY_USER_IP_STR...
    private const int KEY_USER_DATA = 2;

    private const int KEY_IP = 3;

    private const int KEY_USER_AGENT = 6;

    private const int KEY_LINK_BACK = 7;

    private const int KEY_HOSTNAME = 8;

    private const int KEY_PARAMS = 9;

    private const int KEY_VERSION = 10;

    private const int KEY_RESULT = 11;

    private const int VERSION_IFRAME = 2;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
    ) {
    }

    // All three keys set and well-formed: a site that configured none of them has no gate at all, which is what a site selling nothing for adults expects
    public function isEnabled(): bool
    {
        return null !== $this->getKeys();
    }

    // Whether what an item declaring this age holds has to wait behind the gate: an item of age, on a site that configured go.cam, for a visitor not verified yet. The age is the free text an editor typed ("18+", "16", "3-6"), read by its leading number
    public function isGateClosed(mixed $age): bool
    {
        $isAdult = (\is_int($age) || \is_string($age)) && 1 === preg_match('/^\s*(\d+)/', (string) $age, $matches) && (int) $matches[1] >= self::ADULT_AGE;

        return $isAdult && $this->isEnabled() && !$this->isVerified();
    }

    // Verified during this session, or within the lifetime of the cookie a verification leaves
    public function isVerified(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        // The cookie first: a returning visitor already verified opens no session on every page of age
        if ($this->isCookieValid((string) $request->cookies->get(self::COOKIE_NAME))) {
            return true;
        }

        return $request->hasSession() && true === $request->getSession()->get(self::SESSION_VERIFIED);
    }

    // The url go.cam's frame opens, carrying the session's nonce, so none without a session: the result has to bring it back, so a result obtained by someone else cannot be replayed here. Drawn once per session and spent on success, so two tabs share it rather than the last one voiding the first's
    public function getIframeUrl(): ?string
    {
        $keys = $this->getKeys();
        $request = $this->requestStack->getCurrentRequest();
        if (null === $keys || null === $request || !$request->hasSession()) {
            return null;
        }

        $url = $request->attributes->get(self::REQUEST_URL);
        if (\is_string($url)) {
            return $url;
        }

        $session = $request->getSession();
        $nonce = $session->get(self::SESSION_NONCE);
        if (!\is_string($nonce)) {
            $nonce = bin2hex(random_bytes(16));
            $session->set(self::SESSION_NONCE, $nonce);
        }

        $data = [
            'requestTime' => $this->clock->now()->getTimestamp(),
            self::KEY_USER_DATA => json_encode(['userId' => 0, 'nonce' => $nonce], \JSON_THROW_ON_ERROR),
            self::KEY_IP => (string) $request->getClientIp(),
            self::KEY_USER_AGENT => (string) $request->headers->get('User-Agent'),
            self::KEY_LINK_BACK => $request->getUri(),
            self::KEY_HOSTNAME => $request->getHost(),
            // No e-mail anywhere, neither as a way to verify nor as the fallback of a failed one: the point is that nobody learns who the visitor is
            self::KEY_PARAMS => [
                'verificationTypeList' => ['selfie', 'scanId', 'nfcId'],
                'verificationTypeDefault' => 'selfie',
                'useEmailAsFallbackVerification' => false,
            ],
            self::KEY_VERSION => self::VERSION_IFRAME,
        ];

        $url = \sprintf(self::IFRAME_URL, $this->getLanguage($request), $keys['partnerId'], rawurlencode($this->seal($data, $keys)));
        $request->attributes->set(self::REQUEST_URL, $url);

        return $url;
    }

    // Checks the result go.cam handed the browser, and marks the session verified when it holds: signed with our key, carrying our nonce, recent, and a success
    public function confirm(string $payload): bool
    {
        $keys = $this->getKeys();
        $request = $this->requestStack->getCurrentRequest();
        if (null === $keys || null === $request || !$request->hasSession()) {
            return false;
        }

        $session = $request->getSession();
        $nonce = $session->get(self::SESSION_NONCE);
        $data = $this->open($payload, $keys);
        if (!\is_string($nonce) || null === $data || !$this->isSuccess($data, $nonce)) {
            return false;
        }

        $session->remove(self::SESSION_NONCE);
        $session->set(self::SESSION_VERIFIED, true);

        return true;
    }

    // The cookie a verification leaves, signed with the kernel secret so a visitor cannot write one themselves
    public function createCookie(): Cookie
    {
        $expires = $this->clock->now()->getTimestamp() + self::COOKIE_LIFETIME;

        return Cookie::create(self::COOKIE_NAME, $expires . '.' . $this->sign((string) $expires))
            ->withExpires($expires)
            ->withSecure(true)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    // Nonce sent back, result recent enough, and go.cam's success state
    private function isSuccess(array $data, string $nonce): bool
    {
        $userData = json_decode(\is_string($data[self::KEY_USER_DATA] ?? null) ? $data[self::KEY_USER_DATA] : '', true);
        $returnedNonce = \is_array($userData) && \is_string($userData['nonce'] ?? null) ? $userData['nonce'] : '';
        $age = abs($this->clock->now()->getTimestamp() - (int) ($data['requestTime'] ?? 0));
        $state = \is_array($data[self::KEY_RESULT] ?? null) ? (int) ($data[self::KEY_RESULT]['stateInt'] ?? 0) : 0;

        return hash_equals($nonce, $returnedNonce) && $age <= self::RESULT_MAX_AGE && self::STATE_SUCCESS === $state;
    }

    // Encrypts then signs: base64url(hmac(iv . ciphertext) . iv . ciphertext), go.cam's format both ways
    private function seal(array $data, array $keys): string
    {
        $iv = random_bytes(16);
        $ciphertext = (string) openssl_encrypt(json_encode($data, \JSON_THROW_ON_ERROR), self::CIPHER, $keys['cipherKey'], \OPENSSL_RAW_DATA, $iv);
        $buffer = $iv . $ciphertext;

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $buffer, $keys['hmacKey'], true) . $buffer), '+/', '-_'), '=');
    }

    // The signature is checked before anything is decrypted, so a tampered payload is never even read
    private function open(string $payload, array $keys): ?array
    {
        $buffer = base64_decode(strtr($payload, '-_', '+/'), true);
        if (false === $buffer || \strlen($buffer) <= self::HMAC_SIZE + 16) {
            return null;
        }

        $signed = substr($buffer, self::HMAC_SIZE);
        if (!hash_equals(hash_hmac('sha256', $signed, $keys['hmacKey'], true), substr($buffer, 0, self::HMAC_SIZE))) {
            return null;
        }

        $json = openssl_decrypt(substr($signed, 16), self::CIPHER, $keys['cipherKey'], \OPENSSL_RAW_DATA, substr($signed, 0, 16));
        $data = false === $json ? null : json_decode($json, true);

        return \is_array($data) ? $data : null;
    }

    // Expiry and signature, the expiry still ahead
    private function isCookieValid(string $value): bool
    {
        $parts = explode('.', $value, 2);
        if (2 !== \count($parts) || !ctype_digit($parts[0])) {
            return false;
        }

        return (int) $parts[0] > $this->clock->now()->getTimestamp() && hash_equals($this->sign($parts[0]), $parts[1]);
    }

    // Signs a cookie value with the kernel secret, scoped so no other hmac of the site can stand for it
    private function sign(string $value): string
    {
        return hash_hmac('sha256', 'age-verified|' . $value, $this->secret);
    }

    // The visitor's language when go.cam speaks it, English otherwise
    private function getLanguage(Request $request): string
    {
        $language = substr($request->getLocale(), 0, 2);

        return \in_array($language, self::LANGUAGES, true) ? $language : 'en';
    }

    // The three keys, binary, or null when one is missing or not the hex go.cam hands out
    private function getKeys(): ?array
    {
        $partnerId = $this->readConfig('age-verification-partner-id');
        $cipherKey = $this->readConfig('age-verification-cipher-key');
        $hmacKey = $this->readConfig('age-verification-hmac-key');
        if (null === $partnerId || !ctype_digit($partnerId) || !$this->isHex($cipherKey, 64) || !$this->isHex($hmacKey)) {
            return null;
        }

        return ['partnerId' => $partnerId, 'cipherKey' => hex2bin((string) $cipherKey), 'hmacKey' => hex2bin((string) $hmacKey)];
    }

    // Hex of an even length, and of the expected one when given
    private function isHex(?string $value, ?int $length = null): bool
    {
        return null !== $value && ctype_xdigit($value) && 0 === \strlen($value) % 2 && (null === $length || $length === \strlen($value));
    }

    // An unseeded config key reads as null, an emptied one as '' - neither is a value
    private function readConfig(string $key): ?string
    {
        if (!$this->configService->hasParameter($key)) {
            return null;
        }

        $value = $this->configService->get($key);

        return null === $value || '' === $value ? null : trim((string) $value);
    }
}
