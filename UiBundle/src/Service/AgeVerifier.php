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
use c975L\UiBundle\Contract\AgeVerificationProviderInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

// Age verification whatever service performs it (see AgeVerificationProviderInterface, "age-verification-provider" naming the one in use): the site never learns who the visitor is, only that the service found them of age. What is decided here is the service's business nowhere: the nonce the result has to bring back, the session and the cookie a verification leaves
class AgeVerifier
{
    use ConfigChosenProviderTrait;

    public const int ADULT_AGE = 18;

    public const string COOKIE_NAME = 'ui_age_verified';

    private const string SESSION_NONCE = 'ui_age_verification_nonce';

    private const string SESSION_VERIFIED = 'ui_age_verified';

    private const string REQUEST_URL = '_ui_age_verification_url';

    // Thirty days before a verified visitor is asked again, whatever the provider
    private const int COOKIE_LIFETIME = 30 * 86400;

    /**
     * @param iterable<AgeVerificationProviderInterface> $providers
     */
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
        #[AutowireIterator('c975l.age_verification_provider')]
        iterable $providers = [],
    ) {
        $this->indexProviders($providers);
    }

    // The provider "age-verification-provider" names, when it is registered and holds its credentials: null leaves every gate open, which is what a site selling nothing for adults expects
    public function getProvider(): ?AgeVerificationProviderInterface
    {
        $provider = $this->namedProvider('age-verification-provider');

        return $provider instanceof AgeVerificationProviderInterface && $provider->isConfigured() ? $provider : null;
    }

    public function isEnabled(): bool
    {
        return null !== $this->getProvider();
    }

    // Whether what an item declaring this age holds has to wait behind the gate: an item of age, on a site with a provider in use, for a visitor not verified yet. The age is the free text an editor typed ("18+", "16", "3-6"), read by its leading number
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

    // The url the provider's frame opens, carrying the session's nonce, so none without a session: the result has to bring it back, so a result obtained by someone else cannot be replayed here. Drawn once per session and spent on success, so two tabs share it rather than the last one voiding the first's
    public function getIframeUrl(): ?string
    {
        $provider = $this->getProvider();
        $request = $this->requestStack->getCurrentRequest();
        if (null === $provider || null === $request || !$request->hasSession()) {
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

        $url = $provider->getFrameUrl($request, $nonce);
        $request->attributes->set(self::REQUEST_URL, $url);

        return $url;
    }

    // Marks the session verified when the provider vouches for the result and it carries this session's nonce
    public function confirm(string $payload): bool
    {
        $provider = $this->getProvider();
        $request = $this->requestStack->getCurrentRequest();
        if (null === $provider || null === $request || !$request->hasSession()) {
            return false;
        }

        $session = $request->getSession();
        $nonce = $session->get(self::SESSION_NONCE);
        $returnedNonce = $provider->readVerifiedNonce($payload);
        if (!\is_string($nonce) || null === $returnedNonce || !hash_equals($nonce, $returnedNonce)) {
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
}
