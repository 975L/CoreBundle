<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implement this to have your bundle's own urls written to public/sitemap-<getSitemapName()>.xml and declared in the site's sitemap-index.xml, without the app having to list your sitemap command by hand - collected by SitemapWriter, run by the c975l:sitemaps:create command and the "Create sitemaps" dashboard shortcut, see readme. Both this contract and the writer live here rather than in SiteBundle so any combination of bundles gets its sitemaps and its index, SiteBundle installed or not.
interface SitemapProviderInterface
{
    // Name identifying the sub-sitemap, used as-is for the file name: 'book' gives public/sitemap-book.xml. Keep it short and stable, it ends up in a public url.
    public function getSitemapName(): string;

    // Urls to declare, 'priority' on the admin's 0-10 scale and converted by SitemapWriter, which also defaults a missing 'lastmod'/'changefreq'/'priority' ([] writes nothing). Optional 'title'/'description' feed llms.txt only, optional 'videos' are the page's schema.org VideoObjects written as <video:video>, one missing name/description/thumbnailUrl/embedUrl dropped
    /** @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: int, title?: string, description?: string, videos?: list<array<string, string>>}> */
    public function getUrls(): array;
}
