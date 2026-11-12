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
use c975L\UiBundle\Contract\CaptchaProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;

// The public forms' anti-bot check whatever service performs it (see CaptchaProviderInterface, "captcha-provider" naming the one in use): what CaptchaType, its constraint and the privacy policy ask, never the service's own business. See CaptchaType/Validator\Constraints\Captcha
class CaptchaVerifier
{
    use ConfigChosenProviderTrait;

    /**
     * @param iterable<CaptchaProviderInterface> $providers
     */
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly RequestStack $requestStack,
        #[AutowireIterator('c975l.captcha_provider')]
        iterable $providers = [],
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment = 'prod',
    ) {
        $this->indexProviders($providers);
    }

    // The provider "captcha-provider" names, when it is registered and holds its keys: null leaves the forms unprotected by any captcha, the honeypot and timing checks still on
    public function getProvider(): ?CaptchaProviderInterface
    {
        $provider = $this->namedProvider('captcha-provider');

        return $provider instanceof CaptchaProviderInterface && $provider->isConfigured() ? $provider : null;
    }

    // A provider in use, never in "dev", whose database is a copy of production's: its keys only accept the site's own domain, not localhost
    public function isEnabled(): bool
    {
        return 'dev' !== $this->environment && $this->isConfigured();
    }

    // A provider in use, whatever the environment: what the privacy policy reads, the production site's own text not depending on where it is previewed
    public function isConfigured(): bool
    {
        return null !== $this->getProvider();
    }

    public function getSiteKey(): ?string
    {
        return $this->getProvider()?->getSiteKey();
    }

    // Fails closed: no provider or no token rejects the submission, the constraint skipping the check altogether when none is enabled
    public function verify(?string $token): bool
    {
        $provider = $this->getProvider();
        if (null === $provider || null === $token || '' === $token) {
            return false;
        }

        return $provider->verify($token, $this->requestStack->getCurrentRequest()?->getClientIp());
    }
}
