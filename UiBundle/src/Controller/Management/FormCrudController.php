<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Form\FormFieldType;
use c975L\UiBundle\Form\FormLinkType;
use c975L\UiBundle\Form\FormOutputType;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use c975L\UiBundle\Registry\FormActionRegistry;
use c975L\UiBundle\Service\ExpressionEvaluator;
use c975L\UiBundle\Service\FormFieldNamer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// Generic "manage any Form" admin screen - unlike a bundle owning its own dedicated CrudController scoped to one hardcoded name (e.g. ContactFormBundle's former ContactFormCrudController), this one lists/creates/edits every c975L\UiBundle\Entity\Form. A seeded, restricted Form (see Form::$restricted) keeps its "name" locked and can't be deleted from here - same spirit as FormField::$restricted for individual fields
class FormCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly FormFieldNamer $formFieldNamer,
        private readonly FormActionRegistry $actionRegistry,
        private readonly ExpressionEvaluator $expressionEvaluator,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Form::class;
    }

    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Form) {
            $this->formFieldNamer->nameFields($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Form) {
            $this->formFieldNamer->nameFields($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    // Nothing to reconcile on a brand new Form, but its names still have to be derived before validation runs - see addNamingListener()
    #[\Override]
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        $this->addNamingListener($formBuilder);

        return $formBuilder;
    }

    // Names every field and output while the submitted data is being bound, at a priority that runs before Symfony's own validation listener (POST_SUBMIT, priority 0). Doing it in persistEntity/updateEntity, as this screen used to, is too late for ValidExpressionsValidator: it would check the formulas against the names the rows carried BEFORE the save, and so miss exactly the rename that breaks them
    private function addNamingListener(FormBuilderInterface $formBuilder): void
    {
        $formBuilder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getData();
            if ($form instanceof Form) {
                $this->formFieldNamer->nameFields($form);
            }
        }, 10);
    }

    // Removing the very last field also leaves nothing submitted at all for "fields" (an HTML form can't represent an empty array, only an absent key), which has to be normalized to [] below or Symfony skips add/remove handling entirely for the whole field - same reconciliation as ContactFormCrudController/PageCrudController used for their own collections
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        $this->addNamingListener($formBuilder);

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $form = $event->getForm()->getData();
            if ($form instanceof Form) {
                // A restricted field (see FormField::isRestricted()) is never removed, even from a tampered request
                CollectionReconciler::pruneRemoved(
                    $form->getFields(),
                    $data['fields'] ?? [],
                    static function (FormField $field) use ($form): void {
                        if (!$field->isRestricted()) {
                            $form->removeField($field);
                        }
                    }
                );
            }

            if ($form instanceof Form) {
                // An output carries no "restricted" notion: every row an admin added, they can remove
                CollectionReconciler::pruneRemoved(
                    $form->getOutputs(),
                    $data['outputs'] ?? [],
                    static function (FormOutput $output) use ($form): void {
                        $form->removeOutput($output);
                    }
                );
            }

            // Same for "links", whose last row being removed would otherwise leave Form::setLinks() never called and the old links in place
            foreach (['fields', 'outputs', 'links'] as $collection) {
                if (!isset($data[$collection])) {
                    $data[$collection] = [];
                    $event->setData($data);
                }
            }
        });

        return $formBuilder;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        $isRestricted = $entity instanceof Form && $entity->isRestricted();

        $actionKeys = $this->actionRegistry->getKeys();

        return [
            IdField::new('id')
                ->hideOnForm(),
            TextField::new('name')
                ->setLabel(t('label.name', [], 'ui'))
                ->setFormTypeOption('disabled', $isRestricted),
            ChoiceField::new('action')
                ->setLabel(t('label.action', [], 'ui'))
                ->setChoices(array_combine($actionKeys, $actionKeys))
                ->setFormTypeOption('required', false)
                ->setHelp(t('label.action_help', [], 'ui')),
            TextareaField::new('actionConfigJson')
                ->setLabel(t('label.action_config', [], 'ui'))
                ->setFormTypeOption('required', false)
                ->setHelp(t('label.action_config_help', [], 'ui'))
                ->hideOnIndex(),
            BooleanField::new('enabled')
                ->setLabel(t('label.form_enabled', [], 'ui'))
                ->renderAsSwitch(true),
            BooleanField::new('restricted')
                ->setLabel(t('label.restricted', [], 'ui'))
                ->setFormTypeOption('disabled', true)
                ->hideOnIndex(),
            CollectionField::new('fields')
                ->setLabel(t('label.fields', [], 'ui'))
                ->setEntryType(FormFieldType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                // Read by assets/js/form-field-template.js to fetch FormFieldTemplateCrudController::catalog() and offer a "pick a ready-made field" select next to this collection's own "+ Add" button - a plain form type option, not "attr", since only "row_attr" lands on the collection's own wrapping div (see EasyAdminBundle's collection_row Twig block), not the widget itself. The picker's own placeholder text is translated server-side here too, rather than hardcoded in JS - same reasoning as Blocks.html.twig's "data-edit-label"
                ->setFormTypeOption('row_attr', [
                    'data-form-field-template-catalog-url' => $this->adminUrlGenerator
                        ->unsetAll()
                        ->setController(FormFieldTemplateCrudController::class)
                        ->setAction('catalog')
                        ->generateUrl(),
                    'data-form-field-template-picker-placeholder' => $this->translator->trans('label.form_field_template_picker_placeholder', [], 'ui'),
                ])
                ->hideOnIndex(),
            // A Form owning at least one of these is a calculator (see Form::isCalculator()): it computes and displays instead of submitting, so it needs no action above. Declared after "fields", whose variables its expressions read
            CollectionField::new('outputs')
                ->setLabel(t('label.outputs', [], 'ui'))
                ->setEntryType(FormOutputType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                // The variable names as they stand right now, spelled out rather than left to be guessed: they are slugs derived from the labels (see FormFieldNamer), so "Prix de l'E85" is neither "prixE85" nor "prix_de_l_e85" but something only the slugger knows. A field added and not yet saved isn't in there, which the help says
                ->setHelp($this->outputsHelp($entity instanceof Form ? $entity : null))
                ->hideOnIndex(),
            // Not a Doctrine association but a virtual property backed by Form::$actionConfig (see Form::getLinks()) - declared after "actionConfigJson" so a save writes the raw JSON first and these links on top of it, never the other way round
            CollectionField::new('links')
                ->setLabel(t('label.form_links', [], 'ui'))
                ->setEntryType(FormLinkType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setHelp(t('label.form_links_help', [], 'ui'))
                ->hideOnIndex(),
        ];
    }

    private function outputsHelp(?Form $form): TranslatableInterface
    {
        $variableNames = null === $form ? [] : $this->expressionEvaluator->variableNames($form);
        if ([] === $variableNames) {
            return t('label.outputs_help', [], 'ui');
        }

        return t('label.outputs_help_variables', ['%variables%' => implode(', ', $variableNames)], 'ui');
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        // A plain toolbar button to FormFieldTemplateCrudController's own index, not a sidebar menu entry - same button as EmailTemplateCrudController's, this is where an admin actually uses the catalog (see the "fields" CollectionField above) so it belongs here too
        $formFieldTemplatesAction = Action::new('formFieldTemplates', t('label.form_field_templates', [], 'ui'), 'fas fa-list-check')
            ->linkToUrl($this->adminUrlGenerator->unsetAll()->setController(FormFieldTemplateCrudController::class)->generateUrl())
            ->createAsGlobalAction();

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $formFieldTemplatesAction)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action->setLabel(false)->setIcon('fas fa-pencil'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action
                ->setLabel(false)
                ->setIcon('fas fa-trash')
                ->displayIf(static fn (Form $form): bool => !$form->isRestricted()))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission('formFieldTemplates', $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-admin'))
            ->overrideTemplate('crud/index', '@c975LUi/management/form_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LUi/management/form_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LUi/management/form_crud_new.html.twig')
        ;
    }
}
