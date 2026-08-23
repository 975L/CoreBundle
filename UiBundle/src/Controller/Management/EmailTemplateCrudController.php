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
use c975L\UiBundle\Registry\EmailAttachmentRegistry;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// Admin screen for every EmailTemplate; a restricted one keeps its "name" locked and can't be deleted here
class EmailTemplateCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly EmailAttachmentRegistry $emailAttachmentRegistry,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailTemplate::class;
    }

    // An absent "blocks" key is normalized to [], an HTML form having no way to submit an empty array
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            // Normalized first, so what follows reads one shape: an HTML form has no way to submit an empty array, so every block deleted comes back as no key at all
            $data['blocks'] = is_array($data['blocks'] ?? null) ? $data['blocks'] : [];

            $emailTemplate = $event->getForm()->getData();
            if ($emailTemplate instanceof EmailTemplate) {
                $data['blocks'] = $this->restoreDataBlocks($emailTemplate, $event->getForm(), $data['blocks']);

                CollectionReconciler::pruneRemoved(
                    $emailTemplate->getBlocks(),
                    $data['blocks'],
                    static fn (EmailBlock $block) => $emailTemplate->removeBlock($block)
                );
            }

            $event->setData($data);
        });

        return $formBuilder;
    }

    /**
     * Puts back the data blocks a submission dropped, on a template a bundle declares.
     *
     * The order's lines, a form's submitted fields: what the email is actually for. An admin moves them, writes
     * around them and edits every sentence, but taking one out leaves an order confirmation that confirms nothing -
     * and the deletion is a DOM row removed in the browser, so refusing it has to happen here, not in the page.
     *
     * Put back into the submitted array rather than spared in the pruning below, and there is a reason: skipping the
     * removal here would still leave Symfony's own allow_delete diffing to call removeBlock() while mapping. A block
     * survives by being present in what was submitted, not by being excused afterwards.
     *
     * The entry is rebuilt whole and filed under the key the form child already carries. A partial entry would null
     * every field it omits - PRE_SUBMIT clears what is missing - and a fresh key would build a second block through
     * the prototype instead of finding the one being restored.
     *
     * @param array<mixed> $submitted
     *
     * @return array<mixed>
     */
    private function restoreDataBlocks(EmailTemplate $emailTemplate, FormInterface $form, array $submitted): array
    {
        // An admin's own template is theirs entirely: the protection covers what a bundle declared and a site was seeded with, the same line the locked name and the hidden delete action already draw
        if (!$emailTemplate->isRestricted() || !$form->has('blocks')) {
            return $submitted;
        }

        $submittedIds = [];
        foreach ($submitted as $entry) {
            if (is_array($entry) && isset($entry['id']) && '' !== $entry['id']) {
                $submittedIds[] = (string) $entry['id'];
            }
        }

        foreach ($form->get('blocks') as $name => $child) {
            $block = $child->getData();
            if (!$block instanceof EmailBlock || null === $block->getId() || !$block->isDataBlock()) {
                continue;
            }

            if (!in_array((string) $block->getId(), $submittedIds, true)) {
                $submitted[$name] = [
                    'id' => (string) $block->getId(),
                    'type' => $block->getType(),
                    'heading' => $block->getHeading(),
                    'level' => $block->getLevel(),
                    'content' => $block->getContent(),
                    'label' => $block->getLabel(),
                    'url' => $block->getUrl(),
                    'alt' => $block->getAlt(),
                    'height' => null === $block->getHeight() ? null : (string) $block->getHeight(),
                    'position' => (string) $block->getPosition(),
                ];
            }
        }

        return $submitted;
    }

    #[\Override]
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
            // Locked on a seeded row for the same reason as the name: the pair is what a bundle looks the e-mail up by, and renaming either would have it send nothing
            TextField::new('locale')
                ->setLabel(t('label.locale', [], 'ui'))
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
            ...$this->attachmentField(),
        ];
    }

    /**
     * The "attach" checkboxes, one per document an installed bundle is able to draw.
     *
     * Spread into the field list rather than hidden when empty: a site whose bundles offer nothing to attach gets
     * no empty box asking a question it has no answer to.
     *
     * @return list<ChoiceField>
     */
    private function attachmentField(): array
    {
        $kinds = $this->emailAttachmentRegistry->getKinds();

        if ([] === $kinds) {
            return [];
        }

        // Translated here and handed over as plain text: a choice label is an array key, which a TranslatableInterface cannot be. What comes back is wording and matches no key, so the catalogue leaves it alone
        $choices = [];
        foreach ($kinds as $kind => $label) {
            $choices[$label->trans($this->translator)] = $kind;
        }

        return [
            ChoiceField::new('attachments')
                ->setLabel(t('label.email_attachments', [], 'ui'))
                ->setHelp(t('description.email_attachments', [], 'ui'))
                ->setChoices($choices)
                ->allowMultipleChoices()
                ->renderExpanded()
                ->hideOnIndex(),
        ];
    }

    #[\Override]
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

    #[\Override]
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

        return new Response($this->withEditButton($this->emailTemplateRenderer->render($emailTemplate), $emailTemplate));
    }

    /**
     * Adds the pencil that takes the preview back to the screen this email is composed on.
     *
     * The same gesture as the one a page block offers, and deliberately not the same machinery: what is being
     * looked at here is an email document opened on its own, with none of the site's stylesheets or controllers
     * to lean on, and the blocks it is made of are all edited on one screen anyway. So one button rather than one
     * per block, written into the document with its styles inline - the only kind an email document carries.
     */
    private function withEditButton(string $html, EmailTemplate $emailTemplate): string
    {
        $url = $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($emailTemplate->getId())
            ->generateUrl();

        $button = sprintf(
            '<a href="%s" title="%s" aria-label="%s" style="position:fixed;top:16px;right:16px;z-index:9999;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%%;background:#1a1a1a;color:#ffffff;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.3);">'
            . '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></a>',
            htmlspecialchars($url, \ENT_QUOTES),
            $label = htmlspecialchars($this->translator->trans('label.edit', [], 'ui'), \ENT_QUOTES),
            $label,
        );

        // Appended when the document carries no closing body tag, which is what a layout-less fallback comes out as
        return str_contains($html, '</body>')
            ? str_replace('</body>', $button . '</body>', $html)
            : $html . $button;
    }
}
