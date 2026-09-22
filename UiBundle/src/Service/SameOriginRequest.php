<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\HttpFoundation\Request;

// What stands in for a csrf token on the public json routes (rating, favorites, site search), which must never open a session on a page served cached and shared: a json body already sends a cross-origin caller through a CORS preflight they don't answer, and this closes the plain form-post a browser would deliver without one
final class SameOriginRequest
{
    // Origin when the browser sent one (it always does on a fetch), the referer's origin otherwise; neither means the request did not come from a page of this site, and is turned down
    public static function isSameOrigin(Request $request): bool
    {
        $expected = $request->getSchemeAndHttpHost();

        $origin = $request->headers->get('Origin');
        if (null !== $origin) {
            return $origin === $expected;
        }

        $referer = $request->headers->get('Referer');

        return null !== $referer && str_starts_with($referer, $expected . '/');
    }

    // A json request of this site, the one shape these routes accept
    public static function isSameOriginJson(Request $request): bool
    {
        return 'json' === $request->getContentTypeFormat() && self::isSameOrigin($request);
    }
}
