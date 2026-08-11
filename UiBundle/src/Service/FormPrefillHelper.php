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

// Lets app code pre-fill (and lock) a generic Form's field(s) before the visitor reaches it - e.g. a listing page's "Contact us about this" link setting the "contact" Form's "subject" field. Session-based on purpose, not a query string: nothing to build/escape into a URL, and it only kicks in for a visitor who actually clicked through from the page that called prefill()
class FormPrefillHelper
{
    private const string SESSION_PREFIX = 'ui_form_prefill_';

    // Call right before redirecting the visitor to the Form's page. $values is field name => value, matching Form::getFields()'s names
    public function prefill(Request $request, string $formName, array $values): void
    {
        $request->getSession()->set(self::SESSION_PREFIX . $formName, $values);
    }

    // Reads the stashed values without clearing them, so they survive a failed-validation re-render the same way a query string naturally would - call clear() once the submission actually succeeds
    public function consume(Request $request, string $formName): array
    {
        return $request->getSession()->get(self::SESSION_PREFIX . $formName, []);
    }

    public function clear(Request $request, string $formName): void
    {
        $request->getSession()->remove(self::SESSION_PREFIX . $formName);
    }
}
