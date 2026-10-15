<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;

// Shared EasyAdmin actions and action tweaks for the Management CRUD controllers
class EasyAdminActionHelper
{
    // Lets the admin back out of a create/edit without saving, back to the index - chain linkToUrl() to point elsewhere, the url taking precedence
    public static function cancel(string $label): Action
    {
        return Action::new('cancel', $label, 'fa fa-times')->linkToCrudAction(Action::INDEX);
    }

    // Turns an EasyAdmin action button into icon-only, the label becoming its hover "title"
    public static function toIconOnly(Action | ActionGroup $action, string $title): Action | ActionGroup
    {
        // Merges with existing attributes (eg. target) instead of overwriting them
        $htmlAttributes = $action->getAsDto()->getHtmlAttributes();

        return $action->setLabel(false)->setHtmlAttributes([...$htmlAttributes, 'title' => $title]);
    }
}
