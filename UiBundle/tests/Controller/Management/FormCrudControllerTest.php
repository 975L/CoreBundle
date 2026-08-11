<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\FormFieldTemplateCrudController;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Form\FormLinkType;
use c975L\UiBundle\Registry\FormActionRegistry;
use c975L\UiBundle\Service\FormFieldNamer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormCrudControllerTest extends TestCase
{
    private function createController(?AdminUrlGeneratorInterface $adminUrlGenerator = null): FormCrudController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Add from a template…');

        return new FormCrudController(
            $configService,
            $this->createStub(FormFieldNamer::class),
            $this->createStub(FormActionRegistry::class),
            new AdminContextProvider(new RequestStack()),
            $adminUrlGenerator ?? $this->createStub(AdminUrlGeneratorInterface::class),
            $translator,
        );
    }

    public function testGetEntityFqcnReturnsForm(): void
    {
        $this->assertSame(Form::class, FormCrudController::getEntityFqcn());
    }

    public function testConfigureActionsGrantsEveryActionToTheAdminRole(): void
    {
        $controller = $this->createController();

        // A real EasyAdmin runtime pre-populates default actions before calling configureActions()
        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $permissions = $actions->getAsDto(null)->getActionPermissions();
        $this->assertSame('ROLE_ADMIN', $permissions[Action::INDEX]);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::NEW]);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::EDIT]);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::DELETE]);
    }

    // Detail adds no information beyond what edit already shows - disabled entirely, and a Cancel action lets the admin back out of a create/edit without saving
    public function testConfigureActionsDisablesDetailAndAddsCancelOnNewAndEdit(): void
    {
        $controller = $this->createController();

        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertContains(Action::DETAIL, $actions->getAsDto(null)->getDisabledActions());
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
    }

    public function testConfigureActionsHidesDeleteForARestrictedForm(): void
    {
        $controller = $this->createController();

        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        )->getAsDto(null);

        $deleteAction = $actions->getActions()[Crud::PAGE_INDEX][Action::DELETE];

        $this->assertNotNull($deleteAction);
        // No public getter for the display callable - read the private property directly
        $reflection = new \ReflectionProperty($deleteAction, 'displayCallable');
        $displayCallable = $reflection->getValue($deleteAction);

        $this->assertFalse($displayCallable(new Form()->setRestricted(true)));
        $this->assertTrue($displayCallable(new Form()->setRestricted(false)));
    }

    // Same global button as EmailTemplateCrudController's - this is where an admin actually uses the catalog
    public function testConfigureActionsAddsAGlobalButtonLinkingToFormFieldTemplates(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->expects($this->atLeastOnce())->method('setController')->with(FormFieldTemplateCrudController::class)->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/form-field-template');

        $controller = $this->createController($urlGenerator);

        $actions = $controller->configureActions(
            Actions::new()->add(Crud::PAGE_INDEX, Action::EDIT)->add(Crud::PAGE_INDEX, Action::DELETE)
        )->getAsDto(null);

        $action = $actions->getActions()[Crud::PAGE_INDEX]['formFieldTemplates'];

        $this->assertNotNull($action);
        $this->assertSame('/management/form-field-template', $action->getUrl());
    }

    // Read by assets/js/form-field-template.js - both the catalog url and the picker's translated placeholder must land on the "fields" collection's own row_attr
    public function testConfigureFieldsCarriesTheFormFieldTemplateCatalogUrlAndPlaceholderOnFields(): void
    {
        $urlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->method('setController')->willReturnSelf();
        $urlGenerator->method('setAction')->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/form-field-template/catalog');

        $controller = $this->createController($urlGenerator);

        $fieldsField = null;
        foreach ($controller->configureFields('new') as $field) {
            if ($field instanceof CollectionField && 'fields' === $field->getAsDto()->getProperty()) {
                $fieldsField = $field;
            }
        }

        $this->assertNotNull($fieldsField);
        $rowAttr = $fieldsField->getAsDto()->getFormTypeOptions()['row_attr'];
        $this->assertSame('/management/form-field-template/catalog', $rowAttr['data-form-field-template-catalog-url']);
        $this->assertSame('Add from a template…', $rowAttr['data-form-field-template-picker-placeholder']);
    }

    // "links" is a virtual property backed by Form::$actionConfig, so it has to be mapped after "actionConfigJson": the raw JSON setter runs first and the links are then written on top of what it produced
    public function testConfigureFieldsEditsTheLinksAsACollectionMappedAfterTheRawActionConfig(): void
    {
        $properties = [];
        $linksField = null;
        foreach ($this->createController()->configureFields(Crud::PAGE_EDIT) as $field) {
            $properties[] = $field->getAsDto()->getProperty();
            if ($field instanceof CollectionField && 'links' === $field->getAsDto()->getProperty()) {
                $linksField = $field;
            }
        }

        $this->assertNotNull($linksField);
        $this->assertSame(FormLinkType::class, $linksField->getAsDto()->getCustomOption(CollectionField::OPTION_ENTRY_TYPE));
        $this->assertGreaterThan(array_search('actionConfigJson', $properties, true), array_search('links', $properties, true));
    }
}
