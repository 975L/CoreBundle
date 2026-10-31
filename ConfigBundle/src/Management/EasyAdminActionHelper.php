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
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

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

    // Words EasyAdmin's batch "Delete" on a screen holding a recycle bin, where it runs the controller's deleteEntity(): from the list it moves the selection to the bin, from the bin it removes it for good - the generic "Delete" EasyAdmin prints says neither. A screen that dropped the action is skipped, as update() would throw on it
    // Handed the url generator, the bin posts its batch with "trash=1", which EasyAdmin's own url drops: deleteEntity() then decides on the screen the selection came from, never on the row alone - a replayed "Move to the recycle bin" on a row already there must not remove it for good, nor a stale "Delete permanently" trash again a row restored meanwhile
    public static function trashBatchDelete(Actions $actions, bool $trash, ?AdminUrlGeneratorInterface $adminUrlGenerator = null): Actions
    {
        if (null === $actions->getAsDto(null)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE)) {
            return $actions;
        }

        // Warning rather than EasyAdmin's danger for the bin, which can be undone: the modal's "Proceed" button follows it, and so does its "cannot be undone" line (see assets/js/batch-action-modal.js)
        $key = $trash ? 'delete_permanently' : 'move_to_trash';

        return $actions->update(Crud::PAGE_INDEX, Action::BATCH_DELETE, static function (Action $action) use ($key, $trash, $adminUrlGenerator): Action {
            $action
                ->setLabel(t('action.' . $key, [], 'config'))
                ->setIcon($trash ? 'fa fa-trash' : 'fa fa-box-archive')
                ->asWarningAction(!$trash);

            // Built when the button is drawn, the generator starting from the bin's own query (filters, search, sort), which batchDelete() redirects back to
            return $trash && null !== $adminUrlGenerator ? $action->linkToUrl(static fn (): string => $adminUrlGenerator->setAction(Action::BATCH_DELETE)->set('trash', 1)->generateUrl()) : $action;
        });
    }
}
