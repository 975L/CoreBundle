<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Twig\Attribute\AsTwigFilter;

// Rewrites the root-relative links and pictures of a rendered email against the site's own address. A mailbox has no page to resolve "/medias/…" against, so a src or an href starting with a single slash is a broken picture and a dead link the moment the message leaves.
//
// Applied to the whole layout rather than fixed template by template: the paths come from asset(), from components building their own src, and from addresses typed relative in the back-office, and the one place they all pass through is the layout wrapping every email.
class AbsoluteUrlsExtension
{
    // "(?<![\w-])" keeps "data-src" out of it, and "(?!/)" leaves "//host/…" alone: it already names a host
    private const string PATTERN = '#(?<![\w-])(src|href)="/(?!/)#i';

    #[AsTwigFilter('absolute_urls', isSafe: ['html'])]
    public function absolute(string $html, ?string $baseUrl): string
    {
        $baseUrl = rtrim((string) $baseUrl, '/');

        // No address configured: the relative paths are left as they are rather than turned into "/…" prefixed by nothing
        if ('' === $baseUrl) {
            return $html;
        }

        return preg_replace_callback(
            self::PATTERN,
            static fn (array $match): string => $match[1] . '="' . $baseUrl . '/',
            $html,
        ) ?? $html;
    }
}
