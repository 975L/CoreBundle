<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\IconOnlyRowActionsExtension;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Registry\AdminControllerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Translation\TranslatorInterface;

class IconOnlyRowActionsExtensionTest extends TestCase
{
    // Builds the extension with a translator answering the key it is asked
    private function extension(): IconOnlyRowActionsExtension
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new IconOnlyRowActionsExtension($translator);
    }

    // Builds an admin context holding the given crud, or none
    private function context(?CrudDto $crud): AdminContext
    {
        // Final classes, built through EasyAdmin's own test factories rather than doubled
        return AdminContext::forTesting(crudContext: new CrudContext($crud, null, null, new AdminControllerRegistry(new ArrayAdapter())));
    }

    // Builds a crud showing its row actions inline or in a dropdown
    private function crud(bool $dropdown): CrudDto
    {
        $crud = new CrudDto();
        $crud->setShowEntityActionsAsDropdown($dropdown);

        return $crud;
    }

    public function testItSupportsAListShowingItsRowActionsInline(): void
    {
        $this->assertTrue($this->extension()->supports($this->context($this->crud(false))));
    }

    // In a dropdown, an icon without its label would be a menu of unnamed lines
    public function testItLeavesADropdownAlone(): void
    {
        $this->assertFalse($this->extension()->supports($this->context($this->crud(true))));
    }

    // A dashboard page or a custom route has no crud to change
    public function testItLeavesAPageWithoutCrudAlone(): void
    {
        $this->assertFalse($this->extension()->supports($this->context(null)));
    }

    // An app's CRUD that configured nothing gets icons on EasyAdmin's own row actions, Detail included
    public function testItTurnsEasyAdminsRowActionsIntoIcons(): void
    {
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);

        $this->extension()->extend($actions, $this->context($this->crud(false)));

        foreach ([Action::EDIT, Action::DELETE, Action::DETAIL] as $name) {
            $action = $actions->getAsDto(null)->getAction(Crud::PAGE_INDEX, $name);
            $this->assertFalse($action->getLabel(), $name);
            $this->assertSame('action.' . $name, $action->getHtmlAttributes()['aria-label'], $name);
        }
    }

    // The "Create" button is no row action and keeps its text
    public function testItLeavesTheGlobalActionsTheirText(): void
    {
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::NEW)->add(Crud::PAGE_INDEX, Action::EDIT);

        $this->extension()->extend($actions, $this->context($this->crud(false)));

        $this->assertNotFalse($actions->getAsDto(null)->getAction(Crud::PAGE_INDEX, Action::NEW)->getLabel());
    }
}
