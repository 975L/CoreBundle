<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// Hands the web debug toolbar the visit's own nonce, so it still runs after a Turbo navigation. The toolbar nonces its two inline scripts with a nonce of its own, freshly generated on every response, and a Turbo visit re-executes them into the already-loaded document - whose CSP only ever knows the nonces of the very first response. The toolbar was therefore blocked on every page reached without a full reload, which is every page of a site running Turbo.
// The same fix as CookieNonceGenerator's, applied to the one nonce that generator does not mint: WebProfilerBundle reuses the value named by these two headers instead of generating any (see ContentSecurityPolicyHandler::getNonces), and removes them from the response on its way out
#[When('dev')]
class ProfilerNonceSubscriber implements EventSubscriberInterface
{
    // The two names WebProfilerBundle reads a ready-made nonce off, one for the toolbar's scripts and one for its stylesheet
    private const string SCRIPT_HEADER = 'X-SymfonyProfiler-Script-Nonce';

    private const string STYLE_HEADER = 'X-SymfonyProfiler-Style-Nonce';

    public static function getSubscribedEvents(): array
    {
        // Between ProfilerListener (-1024), whose X-Debug-Token says the profiler is on this response, and WebDebugToolbarListener (-2048), which reads these headers back off it
        return [KernelEvents::RESPONSE => ['onKernelResponse', -1500]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // No profiler on this response, so nothing would read these two headers back off it - and they would be served to the browser rather than removed
        if (!$response->headers->has('X-Debug-Token') || $response->headers->has(self::SCRIPT_HEADER)) {
            return;
        }

        $request = $event->getRequest();

        $nonce = $request->attributes->get(CspNonceCookieSubscriber::REQUEST_ATTRIBUTE);
        if (!\is_string($nonce)) {
            // SignedCookieListener has already replaced this with the verified raw value, exactly as CookieNonceGenerator reads it
            $nonce = $request->cookies->get(CspNonceCookieSubscriber::cookieName($request));
        }

        // Only ever reuses a nonce this visit already carries: minting one here would come too late for CspNonceCookieSubscriber (priority 10) to send its cookie, and the next request would find nothing to reuse
        if (!\is_string($nonce) || 1 !== preg_match('/^[0-9a-f]{32}$/', $nonce)) {
            return;
        }

        $response->headers->set(self::SCRIPT_HEADER, $nonce);
        $response->headers->set(self::STYLE_HEADER, $nonce);
    }
}
