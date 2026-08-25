<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Controller\Management\UrlMetadataCrudController;
use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Field\OgImageField;
use c975L\UiBundle\Form\OgImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Provider\FieldProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

class UrlMetadataCrudControllerTest extends TestCase
{
    // The roles configs.json declares, each config answering with its own slug so a test can tell which one a permission came from
    private function createController(): UrlMetadataCrudController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $slug): string => 'site-role-admin' === $slug ? 'ROLE_ADMIN' : 'ROLE_EDITOR'
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new UrlMetadataCrudController($configService, $translator);
    }

    // AbstractCrudController::configureFields() only ever calls getDefaultFields() on whatever the container returns for FieldProvider::class - the real one is final readonly, so an anonymous object with that single method stands in for it
    private function createFieldContainer(): Container
    {
        $container = new Container();
        $container->set(FieldProvider::class, new class {
            public function getDefaultFields(string $pageName): iterable
            {
                return [];
            }
        });

        return $container;
    }

    // A real EasyAdmin runtime pre-populates the default actions before calling configureActions(), which update() then assumes are there
    private function configureActions(UrlMetadataCrudController $controller): Actions
    {
        return $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );
    }

    private function fieldsByProperty(UrlMetadataCrudController $controller): array
    {
        $controller->setContainer($this->createFieldContainer());

        $fields = [];
        foreach ($controller->configureFields(Crud::PAGE_NEW) as $field) {
            $fields[$field->getAsDto()->getProperty()] = $field;
        }

        return $fields;
    }

    public function testGetEntityFqcnIsTheUrlMetadataEntity(): void
    {
        $this->assertSame(UrlMetadata::class, UrlMetadataCrudController::getEntityFqcn());
    }

    public function testConfigureFieldsExposesThePathTheTitleTheSummaryAndTheShareImage(): void
    {
        $fields = $this->fieldsByProperty($this->createController());

        $this->assertSame(['id', 'path', 'title', 'summarySocialNetwork', 'ogImage'], array_keys($fields));
    }

    // Nothing is required: a row arrives with its path already set and everything else is filled in as the site is written
    public function testConfigureFieldsRequiresNothingToBeFilledIn(): void
    {
        $fields = $this->fieldsByProperty($this->createController());

        $this->assertFalse($fields['title']->getAsDto()->getFormTypeOption('required'));
        $this->assertFalse($fields['summarySocialNetwork']->getAsDto()->getFormTypeOption('required'));
        $this->assertFalse($fields['ogImage']->getAsDto()->getFormTypeOption('required'));
    }

    // The share image is picked on the write screens, the index listing the paths and what they say
    public function testTheShareImageIsShownOnTheFormsOnlyAndTheIdOnTheIndexOnly(): void
    {
        $fields = $this->fieldsByProperty($this->createController());

        $this->assertSame([Crud::PAGE_NEW, Crud::PAGE_EDIT], array_keys($fields['ogImage']->getAsDto()->getDisplayedOn()->all()));
        $this->assertSame([Crud::PAGE_INDEX], array_keys($fields['id']->getAsDto()->getDisplayedOn()->all()));
    }

    // A TextField carrying that form type broke the edit screen of any row whose image had been uploaded, its configurator refusing to print the Media
    public function testTheShareImageIsDrawnByTheDedicatedFieldAndNotByATextField(): void
    {
        $field = $this->fieldsByProperty($this->createController())['ogImage'];

        $this->assertInstanceOf(OgImageField::class, $field);
        $this->assertSame(OgImageType::class, $field->getAsDto()->getFormType());
    }

    // Describing an url is an editor's job, deleting a description an admin's
    public function testConfigureActionsRequiresAnAdminToDeleteButOnlyAnEditorToWrite(): void
    {
        $permissions = $this->configureActions($this->createController())->getAsDto(null)->getActionPermissions();

        $this->assertSame('ROLE_EDITOR', $permissions[Action::INDEX] ?? null);
        $this->assertSame('ROLE_EDITOR', $permissions[Action::EDIT] ?? null);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::DELETE] ?? null);
    }

    // Detail adds no information beyond what edit already shows
    public function testConfigureActionsDisablesDetail(): void
    {
        $this->assertContains(Action::DETAIL, $this->configureActions($this->createController())->getAsDto(null)->getDisabledActions());
    }

    // Index-page row actions become icon-only (see EasyAdminActionHelper::toIconOnly()), the label moving to the hover "title"
    public function testConfigureActionsSetsTheIndexRowActionsIconOnly(): void
    {
        $actionConfigDto = $this->configureActions($this->createController())->getAsDto(Crud::PAGE_INDEX);

        $this->assertFalse($actionConfigDto->getAction(Crud::PAGE_INDEX, Action::EDIT)->getLabel());
        $this->assertFalse($actionConfigDto->getAction(Crud::PAGE_INDEX, Action::DELETE)->getLabel());
    }

    // A "Cancel" action on the edit page lets the admin back out without saving - the only write screen left, rows never being created by hand
    public function testConfigureActionsAddsCancelOnTheEditScreen(): void
    {
        $actions = $this->configureActions($this->createController());

        $this->assertSame(Action::INDEX, $actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel')->getCrudActionName());
    }

    // The rows come from what the bundles declare (see UrlMetadataProviderInterface): a path typed by hand, one slash apart, would describe an url that does not exist and nothing would say so
    public function testConfigureActionsDisablesNew(): void
    {
        $this->assertContains(Action::NEW, $this->configureActions($this->createController())->getAsDto(null)->getDisabledActions());
    }

    // Shown but never editable: it identifies the url the row describes, and editing it would move a description onto another url
    public function testThePathIsNotEditable(): void
    {
        $this->assertTrue($this->fieldsByProperty($this->createController())['path']->getAsDto()->getFormTypeOption('disabled'));
    }

    public function testConfigureCrudGivesTheEntityTheEditorPermission(): void
    {
        $crud = $this->createController()->configureCrud(Crud::new());

        $this->assertSame('ROLE_EDITOR', $crud->getAsDto()->getEntityPermission());
    }

    // The paths read in order, a listing being looked for by the url it is served under
    public function testConfigureCrudSortsOnThePath(): void
    {
        $crud = $this->createController()->configureCrud(Crud::new());

        $this->assertSame(['path' => 'ASC'], $crud->getAsDto()->getDefaultSort());
    }

    // Both remaining screens carry the explanatory text saying what the table is for - there is no "new" one, rows never being created by hand
    public function testConfigureCrudOverridesBothCrudTemplates(): void
    {
        $templates = $this->createController()->configureCrud(Crud::new())->getAsDto()->getOverriddenTemplates();

        $this->assertSame('@c975LConfig/management/url_metadata_crud_index.html.twig', $templates['crud/index'] ?? null);
        $this->assertSame('@c975LConfig/management/url_metadata_crud_edit.html.twig', $templates['crud/edit'] ?? null);
        $this->assertArrayNotHasKey('crud/new', $templates);
    }

    public function testConfigureFiltersOffersThePathAndTheTitle(): void
    {
        $filters = $this->createController()->configureFilters(Filters::new());

        $this->assertSame(['path', 'title'], array_keys($filters->getAsDto()->all()));
    }
}
