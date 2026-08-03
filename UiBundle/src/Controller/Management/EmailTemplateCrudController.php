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
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Form\EmailBlockType;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

// Admin screen for every EmailTemplate; a restricted one keeps its "name" locked and can't be deleted here
class EmailTemplateCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailTemplate::class;
    }

    // An absent "blocks" key is normalized to [], an HTML form having no way to submit an empty array
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $emailTemplate = $event->getForm()->getData();
            if ($emailTemplate instanceof EmailTemplate) {
                CollectionReconciler::pruneRemoved(
                    $emailTemplate->getBlocks(),
                    $data['blocks'] ?? [],
                    static fn (EmailBlock $block) => $emailTemplate->removeBlock($block)
                );
            }

            if (!isset($data['blocks'])) {
                $data['blocks'] = [];
                $event->setData($data);
            }
        });

        return $formBuilder;
    }

    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        $isRestricted = $entity instanceof EmailTemplate && $entity->isRestricted();

        return [
            IdField::new('id')
                ->hideOnForm(),
            TextField::new('name')
                ->setLabel(t('label.name', [], 'ui'))
                ->setFormTypeOption('disabled', $isRestricted),
            BooleanField::new('restricted')
                ->setLabel(t('label.restricted', [], 'ui'))
                ->setFormTypeOption('disabled', true)
                ->hideOnIndex(),
            CollectionField::new('blocks')
                ->setLabel(t('label.email_blocks', [], 'ui'))
                ->setEntryType(EmailBlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-admin');

        $previewAction = Action::new('preview', false, 'fas fa-envelope-open-text')
            ->linkToCrudAction('preview')
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (EmailTemplate $emailTemplate): bool => !$emailTemplate->getBlocks()->isEmpty());

        // A plain toolbar button to FormFieldTemplateCrudController's own index, not a sidebar menu entry - both catalogs (email templates here, field templates there) live next to each other conceptually (both feed the "form" Block system), no need for a dedicated menu item just for this one
        $formFieldTemplatesAction = Action::new('formFieldTemplates', t('label.form_field_templates', [], 'ui'), 'fas fa-list-check')
            ->linkToUrl($this->adminUrlGenerator->unsetAll()->setController(FormFieldTemplateCrudController::class)->generateUrl())
            ->createAsGlobalAction();

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', t('action.cancel', domain: 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_EDIT, $previewAction)
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_INDEX, $formFieldTemplatesAction)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action->setLabel(false)->setIcon('fas fa-pencil'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => $action
                ->setLabel(false)
                ->setIcon('fas fa-trash')
                ->displayIf(static fn (EmailTemplate $emailTemplate): bool => !$emailTemplate->isRestricted()))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission('preview', $role)
            ->setPermission('formFieldTemplates', $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-admin'))
            ->overrideTemplate('crud/index', '@c975LUi/management/email_template_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LUi/management/email_template_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LUi/management/email_template_crud_new.html.twig')
        ;
    }

    // Renders the compiled body with its placeholders left untouched, so no real send is needed to check it
    #[AdminRoute('/{entityId}/preview')]
    public function preview(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $emailTemplate = $context->getEntity()->getInstance();

        return new Response($this->emailTemplateRenderer->render($emailTemplate));
    }
}
