<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Contract\AgeVerificationProviderInterface;
use c975L\UiBundle\Service\AgeVerifier;
use Twig\Attribute\AsTwigFunction;

// What the AgeGate component asks: whether an item has to wait behind the gate, which provider verifies, and the url its frame opens
class AgeVerificationExtension
{
    public function __construct(
        private readonly AgeVerifier $ageVerifier,
    ) {
    }

    // True for an item of age, on a site with an age verification provider in use, shown to a visitor not verified yet
    #[AsTwigFunction('age_gate_closed')]
    public function isGateClosed(mixed $age): bool
    {
        return $this->ageVerifier->isGateClosed($age);
    }

    // The provider in use, for its name told to the visitor and its slug picking its script
    #[AsTwigFunction('age_verification_provider')]
    public function getProvider(): ?AgeVerificationProviderInterface
    {
        return $this->ageVerifier->getProvider();
    }

    // One url per request, drawing its nonce into the session: never inside a cached fragment
    #[AsTwigFunction('age_verification_url')]
    public function getIframeUrl(): ?string
    {
        return $this->ageVerifier->getIframeUrl();
    }
}
