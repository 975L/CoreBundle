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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Names the site's app url schemes in the "form-action" of the page connecting an app: its form is answered by a redirection to the app, which a policy limited to 'self' stops without a word, the button then doing nothing. Only on that page, no other form of the site having any business sending the browser to an app. That page is also never framed
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
        if (!$event->isMainRequest() || self::ROUTE !== $event->getRequest()->attributes->get('_route')) {
            return;
        }

        if (CspFormAction::hasPolicy($response)) {
            CspFormAction::widen($response, array_map(static fn (string $scheme): string => $scheme . ':', $this->appTokenManager->schemes()));
        }

        self::denyFraming($response);
    }

    // Never shown inside another site's frame, whose page would lay its own button over the agreement (clickjacking): a policy of its own next to the site's, every policy sent being enforced, plus the older header for browsers reading only that one
    private static function denyFraming(Response $response): void
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'", false);
    }
}
