<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implement this to have your bundle's latest content served as an Atom feed at /feed/<getFeedName()>.xml and announced in every page's <head> - collected by FeedRenderer, see readme. Lives here rather than in SiteBundle for the same reason as SitemapProviderInterface
interface FeedProviderInterface
{
    // Name identifying the feed, used as-is in its url: 'strip' gives /feed/strip.xml. Lowercase letters only, short and stable
    public function getFeedName(): string;

    // Title of the feed, as a feed reader shows it. Must not query the database: it is read on every page to build the <head> links
    public function getFeedTitle(): string;

    // The latest entries, newest first, at most $limit, 'url' and 'image' absolute. Return [] when the content is off the site (route disabled, no "site-url" configured): the feed is then neither served nor announced
    /** @return list<array{url: string, title: string, updated: \DateTimeInterface, summary?: ?string, image?: ?string}> */
    public function getEntries(int $limit): array;
}
