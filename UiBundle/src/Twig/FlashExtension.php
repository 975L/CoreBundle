<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

// Reading app.flashes starts the session and carries no guard of its own, so a first-time anonymous visitor would otherwise pay a connection to the session store for a bag that is always empty - see CookieNonceGenerator for what that cost on every request
class FlashExtension
{
    public function __construct(
        // Stores RequestStack.
        private readonly RequestStack $requestStack,
    ) {
    }

    // True only for a visitor carrying the session cookie, or a request that started the session itself
    #[AsTwigFunction('ui_can_hold_flash')]
    public function canHoldFlash(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        if ($request->hasPreviousSession()) {
            return true;
        }

        return $request->hasSession() && $request->getSession()->isStarted();
    }
}
