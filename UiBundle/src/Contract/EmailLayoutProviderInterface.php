<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Wraps an EmailTemplateRenderer body in the app's own branded layout, so preview and real send match.
interface EmailLayoutProviderInterface
{
    /**
     * @param string      $bodyHtml the already-rendered, email-safe body
     * @param string|null $locale   the language the body was rendered in, so the layout's own wording follows the
     *                              recipient rather than whichever version the database returns first; null when the
     *                              caller has none, which leaves the layout on the site's default locale
     *
     * @return string the same body wrapped in the app's layout, ready to send
     */
    public function wrap(string $bodyHtml, ?string $locale = null): string;
}
