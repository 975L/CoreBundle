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
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Makes EasyAdmin's own row actions of every list (Edit, Delete, Detail) icon-only, after each CRUD controller has configured its own - an app's CRUD gets the back office's look without a line, one the controller made icon-only keeps its title
class IconOnlyRowActionsExtension implements ActionsExtensionInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Inline rows only: in a dropdown, an icon without its label would be a menu of unnamed lines
    public function supports(AdminContext $context): bool
    {
        $crud = $context->getCrud();

        return null !== $crud && !$crud->showEntityActionsAsDropdown();
    }

    // Runs after the CRUD controller's configureActions(), so an action it added to the index is reached too
    public function extend(Actions $actions, AdminContext $context): void
    {
        EasyAdminActionHelper::iconOnlyDefaults($actions, $this->translator, [Action::EDIT, Action::DELETE, Action::DETAIL]);
    }
}
