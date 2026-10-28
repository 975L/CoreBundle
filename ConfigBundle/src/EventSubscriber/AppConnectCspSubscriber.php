<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use c975L\ConfigBundle\Service\AppTokenManager;
use c975L\ConfigBundle\Service\CspFormAction;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Names the site's app url schemes in the "form-action" of the page connecting an app: its form is answered by a redirection to the app, which a policy limited to 'self' stops without a word, the button then doing nothing. Only on that page, no other form of the site having any business sending the browser to an app
class AppConnectCspSubscriber
{
    private const string ROUTE = 'config_app_connect';

    public function __construct(
        private readonly AppTokenManager $appTokenManager,
    ) {
    }

    // Behind everything that writes a policy: NelmioSecurityBundle's own listener sits at 0, and the WebProfiler rebuilds the header from its directives at -128
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1000)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$event->isMainRequest() || self::ROUTE !== $event->getRequest()->attributes->get('_route') || !CspFormAction::hasPolicy($response)) {
            return;
        }

        CspFormAction::widen($response, array_map(static fn (string $scheme): string => $scheme . ':', $this->appTokenManager->schemes()));
    }
}
