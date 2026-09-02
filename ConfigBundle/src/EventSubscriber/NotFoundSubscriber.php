<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\NotFoundRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

// Records the 404s that came from a link, so a broken one is known before anyone reports it. Monolog is deliberately not that place: its prod handler excludes 404 precisely because the mail it would otherwise send is 99% scanners walking "/wp-admin", and the same noise would drown a table just as well - hence the Referer, which those requests do not carry and a browser following a link always does
class NotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotFoundRepository $notFoundRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Well before Symfony's own ErrorListener (priority -128), which turns the exception into the response - the row is written while the request is still the request that failed
        return [KernelEvents::EXCEPTION => ['onKernelException', 0]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        if (Request::METHOD_GET !== $request->getMethod()) {
            return;
        }

        $referer = (string) $request->headers->get('referer', '');
        $path = $request->getPathInfo();
        if (!$this->isWorthRecording($path, $referer)) {
            return;
        }

        // A failure here is a failure to take a note, and the response being built is an error page that may well need the very connection this just tripped on - including on a site whose migration has not run yet, the table simply not existing. Nothing about it is worth turning a 404 into a 500
        try {
            $this->notFoundRepository->record($path, $referer, $this->isInternal($referer, $request), $this->clock->now());
        } catch (\Throwable) {
            return;
        }
    }

    // What separates a link that broke from the traffic a 404 otherwise attracts: a referer that reads as a web page, a path this site could have served, and both of them short enough to be a url someone published rather than an attack. Shape and nothing more - a referer is written by whoever sent the request and no server can check it came from where it says, so a plausible one is all this proves
    private function isWorthRecording(string $path, string $referer): bool
    {
        if ('' === $referer || !$this->isWebUrl($referer)) {
            return false;
        }

        // The same paths RedirectSubscriber declines to answer for: a missing asset is a deployment matter, not a published url anyone can be sent to
        if (1 === preg_match(Redirect::STATIC_PATH_PATTERN, $path)) {
            return false;
        }

        // Skipped rather than truncated - a path or a referer past the column's length is a probe, and half of one would be a row nobody could act on anyway
        return mb_strlen($path) <= 255 && mb_strlen($referer) <= 255;
    }

    // A referer on this very host reads as a link of ours: the page carrying it is ours to fix, and only these are alerted on (see NotFoundAlertProvider). Taken on trust for want of anything better - the header is the client's to write, so anyone can file their own 404s as broken links of ours and set that alert off. A nuisance on a screen an admin reads, which is what it costs to hear about a link that genuinely broke
    private function isInternal(string $referer, Request $request): bool
    {
        return 0 === strcasecmp((string) $this->hostOf($referer), $request->getHost());
    }

    // The scheme is checked as well as the host: a header is whatever its sender wrote, and "javascript://example.com/..." carries this very host - it would be filed as one of our own broken links and listed as a link to click on
    private function isWebUrl(string $referer): bool
    {
        $scheme = parse_url($referer, \PHP_URL_SCHEME);

        return \in_array($scheme, ['http', 'https'], true) && null !== $this->hostOf($referer);
    }

    private function hostOf(string $referer): ?string
    {
        $host = parse_url($referer, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? $host : null;
    }
}
