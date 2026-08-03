<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * Implement to tell where a named Form is really reachable on the front end, when it is displayed by something richer than this bundle's bare "ui_form_submit" route - SiteBundle answers with the Page carrying a "form" Block pointing at it, an admin-editable per-locale slug. Consumed by the "form_url" Twig function, which falls back on the bare route when no provider knows the name.
 */
interface FormPageUrlProviderInterface
{
    /**
     * Null when this provider has no page for that Form, so the next one gets asked.
     *
     * @param string $formName the Form's own name, e.g. "register" or "reset_password_request"
     */
    public function getFormPageUrl(string $formName): ?string;
}
