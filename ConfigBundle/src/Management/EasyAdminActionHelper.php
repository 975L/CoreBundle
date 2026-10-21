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
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Contracts\Translation\TranslatorInterface;

// Shared EasyAdmin actions and action tweaks for the Management CRUD controllers
class EasyAdminActionHelper
{
    // Lets the admin back out of a create/edit without saving, back to the index - chain linkToUrl() to point elsewhere, the url taking precedence
    public static function cancel(string $label): Action
    {
        return Action::new('cancel', $label, 'fa fa-times')->linkToCrudAction(Action::INDEX);
    }

    // Turns an EasyAdmin action button into icon-only, the label becoming its hover "title" and its accessible name
    public static function toIconOnly(Action | ActionGroup $action, string $title): Action | ActionGroup
    {
        // Merges with existing attributes (eg. target) instead of overwriting them
        $htmlAttributes = $action->getAsDto()->getHtmlAttributes();

        return $action->setLabel(false)->setHtmlAttributes([...$htmlAttributes, 'title' => $title, 'aria-label' => $title]);
    }

    // Turns EasyAdmin's own row actions of the index (Edit and Delete by default) into icon-only, titled by EasyAdmin's label - one the app took off the index is skipped, as EasyAdmin's update() would throw on it
    /** @param list<string> $names */
    public static function iconOnlyDefaults(Actions $actions, TranslatorInterface $translator, array $names = [Action::EDIT, Action::DELETE]): Actions
    {
        foreach ($names as $name) {
            // Read through getAsDto(null): naming a page there pins the dto to it, and a reorder() made afterwards no longer finds its pages
            if (null === $actions->getAsDto(null)->getAction(Crud::PAGE_INDEX, $name)) {
                continue;
            }

            $actions->update(Crud::PAGE_INDEX, $name, static fn (Action $action) => self::toIconOnly(
                $action,
                $translator->trans('action.' . $name, [], 'EasyAdminBundle'),
            ));
        }

        return $actions;
    }
}
