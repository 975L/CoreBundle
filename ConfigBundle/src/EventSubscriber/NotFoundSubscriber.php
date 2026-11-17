<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use c975L\ConfigBundle\Repository\NotFoundRepository;
use c975L\ConfigBundle\Service\NotFoundFilter;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

// Records the 404s that came from a link, so a broken one is known before anyone reports it. Monolog is deliberately not that place: its prod handler excludes 404 precisely because the mail it would otherwise send is 99% scanners walking "/wp-admin", and the same noise would drown a table just as well - hence the Referer, which a browser following a link always sends and most scanners do not. Some forge one, which NotFoundFilter is there for
class NotFoundSubscriber
{
    public function __construct(
        private readonly NotFoundRepository $notFoundRepository,
        private readonly ClockInterface $clock,
        private readonly NotFoundFilter $notFoundFilter,
    ) {
    }

    // Well before Symfony's own ErrorListener (priority -128), which turns the exception into the response - the row is written while the request is still the request that failed
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
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
        if (!$this->notFoundFilter->isWorthRecording($path, $referer)) {
            return;
        }

        // A failure here is a failure to take a note, and the response being built is an error page that may well need the very connection this just tripped on - including on a site whose migration has not run yet, the table simply not existing. Nothing about it is worth turning a 404 into a 500
        try {
            $this->notFoundRepository->record($path, $referer, $this->notFoundFilter->isInternal($referer, $request->getHost()), $this->clock->now());
        } catch (\Throwable) {
            return;
        }
    }
}
