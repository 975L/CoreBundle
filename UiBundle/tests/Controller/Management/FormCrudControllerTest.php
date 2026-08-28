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
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\FormFieldTemplateCrudController;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Form\FormLinkType;
use c975L\UiBundle\Form\FormOutputType;
use c975L\UiBundle\Management\FormExportProvider;
use c975L\UiBundle\Management\FormImportProvider;
use c975L\UiBundle\Registry\FormActionRegistry;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\ExpressionEvaluator;
use c975L\UiBundle\Service\FormFieldNamer;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormCrudControllerTest extends TestCase
{
    private function createController(?AdminUrlGeneratorInterface $adminUrlGenerator = null, ?ExpressionEvaluator $expressionEvaluator = null, ?AdminContextProvider $adminContextProvider = null, ?FormRepository $formRepository = null, ?ContentExporter $contentExporter = null): FormCrudController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Add from a template…');

        return new FormCrudController(
            $configService,
            $this->createStub(FormFieldNamer::class),
            $this->createStub(FormActionRegistry::class),
            $expressionEvaluator ?? $this->createStub(ExpressionEvaluator::class),
            $adminContextProvider ?? new AdminContextProvider(new RequestStack()),
            $adminUrlGenerator ?? $this->createStub(AdminUrlGeneratorInterface::class),
            $translator,
            $formRepository ??= $this->createStub(FormRepository::class),
            new FormExportProvider($formRepository),
            $contentExporter ?? $this->createStub(ContentExporter::class),
        );
    }

    // The two container services AbstractController reads for denyAccessUnlessGranted() and isCsrfTokenValid()
    private function createContainer(bool $granted, bool $validToken): Container
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        $tokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $tokenManager->method('isTokenValid')->willReturn($validToken);

        $container = new Container();
        $container->set('security.authorization_checker', $checker);
        $container->set('security.csrf.token_manager', $tokenManager);

        return $container;
    }

    // The chain exportSelection() walks to send an admin back to the index
    private function createRedirectingUrlGenerator(): AdminUrlGeneratorInterface
    {
        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/forms');

        return $adminUrlGenerator;
    }

    private function createExportContext(): AdminContext
    {
        return AdminContext::forTesting(crudContext: CrudContext::forTesting(
            entityDto: new EntityDto(Form::class, new ClassMetadata(Form::class), null, new Form())
        ));
    }

    // The screen's own gate, which the batch action never gets to skip
    public function testExportSelectionDeniesAccessBelowTheAdminRole(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer(false, true));

        $controller->exportSelection($this->createExportContext(), new BatchActionDto('exportSelection', [1], Form::class, 'token'));
    }

    // A batch action reaches this method with whatever entity the request names
    public function testExportSelectionRefusesAnotherEntity(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer(true, true));

        $controller->exportSelection($this->createExportContext(), new BatchActionDto('exportSelection', [1], \stdClass::class, 'token'));
    }

    // Back to the index rather than an error page, and nothing read from the database on the way
    public function testExportSelectionRedirectsWhenTheCsrfTokenIsInvalid(): void
    {
        $formRepository = $this->createMock(FormRepository::class);
        $formRepository->expects($this->never())->method('findBy');

        $controller = $this->createController($this->createRedirectingUrlGenerator(), formRepository: $formRepository);
        $controller->setContainer($this->createContainer(true, false));

        $response = $controller->exportSelection($this->createExportContext(), new BatchActionDto('exportSelection', [1], Form::class, 'invalid'));

        $this->assertSame('/management/forms', $response->getTargetUrl());
    }

    // The checked Forms, serialized by FormExportProvider and handed to the exporter under the kind FormImportProvider reads back
    public function testExportSelectionHandsTheCheckedFormsToTheExporter(): void
    {
        $form = new Form()
            ->setName('economies-e85')
            ->addField(new FormField()->setName('kilometres-par-an')->setLabel('Kilomètres par an')->setType(FormField::TYPE_RANGE))
            ->addOutput(new FormOutput()->setName('litres')->setLabel('Litres')->setExpression('kilometres_par_an / 100'));

        $formRepository = $this->createStub(FormRepository::class);
        $formRepository->method('findBy')->willReturn([$form]);

        $contentExporter = $this->createMock(ContentExporter::class);
        $contentExporter->expects($this->once())
            ->method('export')
            ->with(FormImportProvider::KIND, $this->callback(function (array $items): bool {
                $this->assertSame('economies-e85', $items[0]['name']);
                $this->assertSame(['kilometres-par-an'], array_column($items[0]['fields'], 'name'));
                $this->assertSame('kilometres_par_an / 100', $items[0]['outputs'][0]['expression']);

                return true;
            }), [])
            ->willReturn(new BinaryFileResponse(tempnam(sys_get_temp_dir(), 'form_export_test_')));

        $controller = $this->createController(formRepository: $formRepository, contentExporter: $contentExporter);
        $controller->setContainer($this->createContainer(true, true));

        $controller->exportSelection($this->createExportContext(), new BatchActionDto('exportSelection', [1], Form::class, 'valid'));
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
        $this->assertSame('ROLE_ADMIN', $permissions['exportSelection']);
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

    // An expression reads the variables the fields above it declare, so the collection editing it is declared after them - the help text below is what spells those variables out
    public function testConfigureFieldsEditsTheOutputsAsACollectionDeclaredAfterTheFields(): void
    {
        $properties = [];
        $outputsField = null;
        foreach ($this->createController()->configureFields(Crud::PAGE_EDIT) as $field) {
            $properties[] = $field->getAsDto()->getProperty();
            if ($field instanceof CollectionField && 'outputs' === $field->getAsDto()->getProperty()) {
                $outputsField = $field;
            }
        }

        $this->assertNotNull($outputsField);
        $this->assertSame(FormOutputType::class, $outputsField->getAsDto()->getCustomOption(CollectionField::OPTION_ENTRY_TYPE));
        $this->assertGreaterThan(array_search('fields', $properties, true), array_search('outputs', $properties, true));
    }

    // A variable is a slug only FormFieldNamer knows how to spell, so the screen lists the current ones rather than leaving them to be guessed
    public function testTheOutputsHelpListsTheFormCurrentVariables(): void
    {
        $evaluator = $this->createStub(ExpressionEvaluator::class);
        $evaluator->method('variableNames')->willReturn(['prix_de_l_essence', 'litres']);

        $help = $this->outputsHelpOf($evaluator, new Form());

        $this->assertSame('label.outputs_help_variables', $help->getMessage());
        $this->assertSame('prix_de_l_essence, litres', $help->getParameters()['%variables%']);
    }

    // A brand new Form has no field yet, hence no variable to name - the help says so instead of printing an empty list
    public function testTheOutputsHelpFallsBackWhenTheFormDeclaresNoVariableYet(): void
    {
        $evaluator = $this->createStub(ExpressionEvaluator::class);
        $evaluator->method('variableNames')->willReturn([]);

        $help = $this->outputsHelpOf($evaluator, new Form());

        $this->assertSame('label.outputs_help', $help->getMessage());
    }

    // The three attributes the outputs collection hands to the client: the guided project's own target, and the names assets/js/formula-variables.js builds its insert-a-variable bar out of
    public function testTheOutputsCollectionCarriesTheVariableNamesForTheClient(): void
    {
        $evaluator = $this->createStub(ExpressionEvaluator::class);
        $evaluator->method('variableNames')->willReturn(['kilometres_par_an', 'litres']);

        $rowAttr = $this->outputsFieldOf($evaluator, new Form())->getFormTypeOption('row_attr');

        $this->assertSame('true', $rowAttr['data-form-outputs-collection']);
        $this->assertSame('["kilometres_par_an","litres"]', $rowAttr['data-form-outputs-variables']);
        $this->assertArrayHasKey('data-form-outputs-variables-hint', $rowAttr);
    }

    private function outputsHelpOf(ExpressionEvaluator $expressionEvaluator, Form $entity): TranslatableMessage
    {
        return $this->outputsFieldOf($expressionEvaluator, $entity)->getHelp();
    }

    // configureFields() reads the Form being edited off the admin context, which AdminContextProvider takes from the current request - so a screen exercised outside EasyAdmin's runtime has to be handed one
    private function outputsFieldOf(ExpressionEvaluator $expressionEvaluator, Form $entity): FieldDto
    {
        $context = AdminContext::forTesting(crudContext: CrudContext::forTesting(
            entityDto: new EntityDto(Form::class, new ClassMetadata(Form::class), null, $entity)
        ));

        $request = new Request();
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);
        $requestStack = new RequestStack([$request]);

        $controller = $this->createController(null, $expressionEvaluator, new AdminContextProvider($requestStack));

        foreach ($controller->configureFields(Crud::PAGE_EDIT) as $field) {
            if ($field instanceof CollectionField && 'outputs' === $field->getAsDto()->getProperty()) {
                return $field->getAsDto();
            }
        }

        $this->fail('No "outputs" collection field was configured.');
    }
}
