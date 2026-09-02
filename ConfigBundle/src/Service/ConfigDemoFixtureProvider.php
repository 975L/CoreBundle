<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Entity\NotFound;
use c975L\ConfigBundle\Entity\Redirect;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;

/**
 * The addresses a demo site is asked for and does not serve, and the one it has been taught to answer for.
 *
 * These are not content: they are what arrives from outside, the only rows of this bundle a demo cannot generate
 * for itself - a 404 is recorded when someone asks for a page, and a demo nobody has browsed has never been asked
 * for one. Everything else this bundle lists is written by a command a real deployment already runs, and is left
 * to it: the url descriptions by "c975l:url-metadata:sync", the health check by "c975l:health-check:run".
 *
 * The three are the three cases the screen exists to tell apart: an address a search engine still holds, one a
 * visitor mistyped, and one the site itself links to - the last being the one worth fixing today.
 */
class ConfigDemoFixtureProvider implements DemoFixtureProviderInterface
{
    // Written down rather than taken from the clock: a demo is reloaded often, and "first seen four days ago" would say something else in every take of the same recorded sequence
    private const string FIRST_SEEN = '2026-02-11 07:22:00';
    private const string LAST_SEEN = '2026-02-24 19:05:00';

    public function getDemoFixtures(): iterable
    {
        // An address a release moved and a search engine still hands out: many hits, an outside referer, and the row the guided project turns into a redirection
        yield $this->notFound('/nos-tarifs', 'https://www.google.com/', false, 46);

        // Someone's typing, seen twice and worth nothing: what the screen teaches to leave alone
        yield $this->notFound('/contactt', 'https://www.bing.com/', false, 2);

        // The site's own doing, which is the only kind that is a bug rather than a fact: a link on a page points at an address the site does not serve
        yield $this->notFound('/documentation/pdf', '/services', true, 8);

        // The answer the screen writes, shown already made so the redirections list is not empty beside the 404s
        yield new Redirect()
            ->setFromPath('/anciennes-realisations')
            ->setToUrl('/realisations')
            ->setPermanent(true)
        ;
    }

    private function notFound(string $path, string $referer, bool $internal, int $hits): NotFound
    {
        return new NotFound()
            ->setPath($path)
            ->setReferer($referer)
            ->setInternal($internal)
            ->setHits($hits)
            ->setFirstSeen(new \DateTime(self::FIRST_SEEN))
            ->setLastSeen(new \DateTime(self::LAST_SEEN))
        ;
    }
}
