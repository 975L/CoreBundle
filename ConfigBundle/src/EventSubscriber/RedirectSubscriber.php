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
use c975L\ConfigBundle\Repository\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class RedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RedirectRepository $redirectRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 33 runs before RouterListener (priority 32)
        return [KernelEvents::REQUEST => ['onKernelRequest', 33]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if ('/' === $path || $this->isStaticAsset($path)) {
            return;
        }

        $redirect = $this->resolve($path);

        // A trailing slash does not make another url: "/contact/" and "/contact" are the same page to whoever published the link, and a row per variant would double the table. Tried only once nothing matched, so a row written with its own slash still answers for itself - and only an exact row can be gained this way, a prefix already covering whatever sits below it, slash or no slash
        if (null === $redirect && '/' !== $path && str_ends_with($path, '/')) {
            $redirect = $this->resolve(rtrim($path, '/'));
        }

        if (null === $redirect) {
            return;
        }

        // Left to the exception listener like any other http exception, so the app's own error page renders it - there is no url to send anyone to, which is the whole point of the row
        if ($redirect->isGone()) {
            throw new GoneHttpException();
        }

        // The column is nullable and only the validator forbids an empty destination on a non-gone row, so a row written around it (import, hand-made SQL) would otherwise take the whole path down with a TypeError - and this runs before the router, hence a hard 500 rather than an error page. The request simply carries on as if no row matched
        $toUrl = (string) $redirect->getToUrl();
        if ('' === $toUrl) {
            return;
        }

        $toUrl = $this->applyTail($path, (string) $redirect->getFromPath(), $toUrl);

        $status = $redirect->isPermanent() ? 301 : 302;
        $event->setResponse(new RedirectResponse($toUrl, $status));
    }

    // Redirects are a url-of-a-page feature, so a request for a file that the web server did not find gets no query at all: Doctrine connects lazily and this subscriber runs on every request, so without this it is the one thing turning a missing asset into a database connection - and a page full of stale image urls into a burst of them. Uploads are left out of it: a removed file under "/medias" is a url someone did publish, and the entity refuses a row on the two prefixes covered here rather than letting it be written and never fire
    private function isStaticAsset(string $path): bool
    {
        return 1 === preg_match(Redirect::STATIC_PATH_PATTERN, $path);
    }

    // A "*" on both sides carries the tail of the path over to the destination: "/character/*" -> "/personnages/*" sends "/character/tuor" to "/personnages/tuor", which is what a renamed url tree needs. A destination without it keeps sending the whole tree to one url, which is what a tree folded into a single page needs - both are wanted, and the "*" is what tells them apart
    private function applyTail(string $path, string $fromPath, string $toUrl): string
    {
        if (!str_ends_with($fromPath, '*') || !str_ends_with($toUrl, '*')) {
            return $toUrl;
        }

        // A row matched on its literal path ("/character/*" requested as such, which resolve() treats as an exact match) carries its own star over as the tail, landing on "/personnages/*" - the destination the row states, which is what makes such a row checkable by requesting it as written
        return rtrim($toUrl, '*') . substr($path, \strlen(rtrim($fromPath, '*')));
    }

    // An exact fromPath always wins, so a single "/apidoc/*" row can cover a whole tree of removed urls while one of them keeps its own specific answer. Among prefixes the longest one wins, so "/apidoc/c975L/*" still beats a broader "/apidoc/*" covering it
    private function resolve(string $path): ?Redirect
    {
        $prefixMatch = null;
        $prefixMatchLength = -1;

        foreach ($this->redirectRepository->findCandidatesForPath($path) as $redirect) {
            $fromPath = (string) $redirect->getFromPath();

            if ($fromPath === $path) {
                return $redirect;
            }

            $prefix = rtrim($fromPath, '*');
            if (str_starts_with($path, $prefix) && \strlen($prefix) > $prefixMatchLength) {
                $prefixMatch = $redirect;
                $prefixMatchLength = \strlen($prefix);
            }
        }

        return $prefixMatch;
    }
}
