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
use c975L\UiBundle\Entity\FormFieldTemplate;
use c975L\UiBundle\Form\FormFieldType;
use c975L\UiBundle\Repository\FormFieldTemplateRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\HttpFoundation\JsonResponse;

use function Symfony\Component\Translation\t;

// Generic "manage any FormFieldTemplate" admin screen, same spirit as FormCrudController/EmailTemplateCrudController: lists/creates/edits every c975L\UiBundle\Entity\FormFieldTemplate, the catalog picked from on a Form's own "fields" collection (see assets/js/form-field-template.js). A seeded, restricted FormFieldTemplate keeps its "name" locked and can't be deleted from here, but every other property stays editable so a seeded default (e.g. the "email" placeholder text) can still be tuned per site
class FormFieldTemplateCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AdminContextProvider $adminContextProvider,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FormFieldTemplate::class;
    }

    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        $isRestricted = $entity instanceof FormFieldTemplate && $entity->isRestricted();

        return [
            IdField::new('id')
                ->hideOnForm(),
            TextField::new('name')
                ->setLabel(t('label.name', [], 'ui'))
                ->setFormTypeOption('disabled', $isRestricted),
            TextField::new('fieldLabel')
                ->setLabel(t('label.field_label', [], 'ui')),
            TextField::new('placeholder')
                ->setLabel(t('label.field_placeholder', [], 'ui'))
                ->setFormTypeOption('required', false)
                ->hideOnIndex(),
            ChoiceField::new('type')
                ->setLabel(t('label.field_type', [], 'ui'))
                // setTranslatableChoices(), not setChoices(): a plain choice array's keys only translate correctly when EasyAdmin's own CRUD-level translation domain is "ui" (it isn't, by default) - see FormFieldType::translatableTypeChoices()
                ->setTranslatableChoices(FormFieldType::translatableTypeChoices()),
            BooleanField::new('required')
                ->setLabel(t('label.field_required_default', [], 'ui'))
                ->setHelp(t('label.field_required_default_help', [], 'ui')),
            BooleanField::new('restricted')
                ->setLabel(t('label.restricted', [], 'ui'))
                ->setFormTypeOption('disabled', true)
                ->hideOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', t('action.cancel', domain: 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action->setLabel(false)->setIcon('fas fa-pencil'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action
                ->setLabel(false)
                ->setIcon('fas fa-trash')
                ->displayIf(static fn (FormFieldTemplate $template): bool => !$template->isRestricted()))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-admin'))
            ->overrideTemplate('crud/index', '@c975LUi/management/form_field_template_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LUi/management/form_field_template_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LUi/management/form_field_template_crud_new.html.twig')
        ;
    }

    // Fetched client-side by assets/js/form-field-template.js on a Form's own edit/new screen - deliberately not a full EasyAdmin index/detail response, just the flat shape that JS needs to fill a fresh FormField row
    #[AdminRoute('/catalog', name: 'form_field_template_catalog')]
    public function catalog(FormFieldTemplateRepository $repository): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return new JsonResponse(array_map(
            static fn (FormFieldTemplate $template): array => [
                'name' => $template->getName(),
                'fieldLabel' => $template->getFieldLabel(),
                'placeholder' => $template->getPlaceholder(),
                'type' => $template->getType(),
                'required' => $template->isRequired(),
            ],
            $repository->findAllOrdered(),
        ));
    }
}
