<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Lets any bundle contribute its own Twig form theme(s) to the EasyAdmin dashboard. Needed because EasyAdmin renders every CRUD form with "{% form_theme form with ea.crud.formThemes only %}" (see ConfigBundle's DashboardController::configureCrud) - the "only" keyword means the app-wide twig.form_themes config (Symfony's normal mechanism) is never even consulted there, so a bundle's custom widget (Trix editor, icon picker, "used in"...) has to be registered this way instead to actually apply inside EasyAdmin, not just in plain Symfony forms rendered elsewhere. Implement this and the service is auto-discovered by FormThemeProviderPass - see Readme.
interface FormThemeProviderInterface
{
    /** @return string[] Twig template paths, e.g. "@c975LUi/form/block_theme.html.twig" */
    public function getFormThemes(): array;
}
