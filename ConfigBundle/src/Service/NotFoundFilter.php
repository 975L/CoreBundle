<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Entity\Redirect;

// What separates a link that broke from the traffic a 404 otherwise attracts, shared by NotFoundSubscriber, which records only what passes, and NotFoundCleanupCommand, which deletes the rows recorded before a rule existed. Shape and nothing more - a referer is written by whoever sent the request and no server can check it came from where it says, so a plausible one is all this proves
class NotFoundFilter
{
    // What scanners walk for and this site never serves: dotfiles, WordPress, CGI, GraphQL endpoints and server-side scripts or config files. Scanners forge a referer too ("https://www.binance.com/", "https://google.com/", the url itself), so the shape of the path is what is left to tell them apart
    public const string PROBE_PATH_PATTERN = '#(?:^|/)(?:\.|wp-|cgi-bin|graphql)|\.(?:php\d?|aspx?|axd|jsp|cgi|cc|env|ini|ya?ml|sql|bak|old|log|properties|py|sh|alfa)$#i';

    // Files nobody publishes a link to: scripts, styles, fonts, data, sitemaps and archives - a missing one is a deployment matter or a crawler guessing "/sitemap.xml.gz". Images are left out on purpose, a missing one being linked from a page or another site as any page is
    public const string FILE_PATH_PATTERN = '#\.(?:m?js|css|map|woff2?|ttf|eot|json|xml|txt|gz|zip)$#i';

    // A referer that reads as a web page, a path this site could have served, and both of them short enough to be a url someone published rather than an attack
    public function isWorthRecording(string $path, string $referer): bool
    {
        if (!$this->isWebUrl($referer) || !$this->isPublishablePath($path)) {
            return false;
        }

        // A robots.txt or a sitemap is what a crawler reads, never a page a visitor clicked a link on
        $refererPath = (string) parse_url($referer, \PHP_URL_PATH);
        if (1 === preg_match(self::FILE_PATH_PATTERN, $refererPath)) {
            return false;
        }

        // A page linking to itself does not 404 - a referer naming the very url asked for is forged, "//upload/server/php" for "/upload/server/php/" included
        if (0 === strcasecmp($this->normalize($refererPath), $this->normalize($path))) {
            return false;
        }

        // Skipped rather than truncated - a path or a referer past the column's length is a probe, and half of one would be a row nobody could act on anyway
        return mb_strlen($path) <= 255 && mb_strlen($referer) <= 255;
    }

    // Whether the referer carries this very host, which makes the link one of our own pages' (see NotFoundAlertProvider)
    public function isInternal(string $referer, string $host): bool
    {
        return 0 === strcasecmp((string) $this->hostOf($referer), $host);
    }

    // The paths RedirectSubscriber declines to answer for, the probes and the files no link points to
    private function isPublishablePath(string $path): bool
    {
        return 1 !== preg_match(Redirect::STATIC_PATH_PATTERN, $path)
            && 1 !== preg_match(self::PROBE_PATH_PATTERN, $path)
            && 1 !== preg_match(self::FILE_PATH_PATTERN, $path);
    }

    // The scheme is checked as well as the host: a header is whatever its sender wrote, and "javascript://example.com/..." carries this very host - it would be filed as one of our own broken links and listed as a link to click on
    private function isWebUrl(string $referer): bool
    {
        $scheme = parse_url($referer, \PHP_URL_SCHEME);

        return \in_array($scheme, ['http', 'https'], true) && null !== $this->hostOf($referer);
    }

    // The host of a referer, null when it carries none
    private function hostOf(string $referer): ?string
    {
        $host = parse_url($referer, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? $host : null;
    }

    // Repeated and trailing slashes collapsed, which a forged referer adds and a server ignores
    private function normalize(string $path): string
    {
        return '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
    }
}
