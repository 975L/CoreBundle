<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use c975L\UiBundle\Management\SharedBlockOwnerResolver;
use c975L\UiBundle\Registry\BlockLocationRegistry;
use c975L\UiBundle\Repository\SharedBlockRepository;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\SharedBlockUsage;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// The blocks written once and shown on several pages through a "shared_block" pointer - see SharedBlock. Deleting one still shown somewhere is refused, a page losing a section without anyone deciding it
class SharedBlockCrudController extends AbstractCrudController
{
    /** @var array<string, int>|null pointers per slug, read once per request for the index column and its delete buttons */
    private ?array $usages = null;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly SharedBlockRepository $sharedBlockRepository,
        private readonly SharedBlockUsage $sharedBlockUsage,
        private readonly BlockLocationRegistry $blockLocationRegistry,
        private readonly SluggerInterface $slugger,
        private readonly ContentLocaleScreen $contentLocaleScreen,
        private readonly ContentTranslator $contentTranslator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SharedBlock::class;
    }

    // Removing the very last block submits nothing for "blocks" at all, normalized to [] here or Symfony skips the removal (see MenuCrudController for the same trick)
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        $contentLocale = $this->contentLocale();

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($contentLocale): void {
            $data = $event->getData();
            $sharedBlock = $event->getForm()->getData();
            // A language screen offers neither "+" nor bin, so nothing can have been removed there: reading the absent key as a removal would delete the blocks on a submission carrying only translations
            if (!is_array($data) || !$sharedBlock instanceof SharedBlock || null !== $contentLocale) {
                return;
            }

            CollectionReconciler::pruneRemoved(
                $sharedBlock->getBlocks(),
                $data['blocks'] ?? [],
                static fn (Block $block) => $sharedBlock->removeBlock($block)
            );
            $data['blocks'] ??= [];

            $event->setData($data);
        });

        return $formBuilder;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('crud/index', '@c975LUi/management/shared_block_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LUi/management/shared_block_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LUi/management/shared_block_crud_new.html.twig')
            ->setEntityLabelInSingular(t('label.shared_block', [], 'ui'))
            ->setEntityLabelInPlural(t('label.shared_blocks', [], 'ui'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->setDefaultSort(['name' => 'ASC'])
            ->showEntityActionsInlined()
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Lets the editor back out of a create/edit without saving, as on the other c975L screens
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            // Offered only when no page shows it: delete() refuses the others anyway, the button would only lead to that refusal
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(fn (SharedBlock $sharedBlock): bool => 0 === $this->usageCount($sharedBlock)),
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // A batch delete never goes through delete(), so it would bypass the guard
            ->disable(Action::DETAIL, Action::BATCH_DELETE)
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        // A language screen: the blocks alone, written in that language through the form they are always edited with (see BlockType's "translation_locale"). Neither add nor delete, a block taken away there being taken away from every language at once; the name is never read by a visitor, so it is not offered
        $contentLocale = Crud::PAGE_EDIT === $pageName ? $this->contentLocale() : null;
        if (null !== $contentLocale) {
            yield CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'ui'))
                ->setColumns('col-12')
                ->setEntryType(BlockType::class)
                ->allowAdd(false)
                ->allowDelete(false)
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('entry_options.translation_locale', $contentLocale);

            return;
        }

        yield TextField::new('name')
            ->setLabel(t('label.shared_block_name', [], 'ui'))
            ->setHelp(t('label.shared_block_name_help', [], 'ui'));

        // Set once from the name and never changed after, so a pointer can never be left naming nothing
        yield TextField::new('slug')
            ->setLabel(t('label.shared_block_slug', [], 'ui'))
            ->setFormTypeOption('disabled', true)
            ->hideOnIndex()
            ->onlyWhenUpdating();

        yield IntegerField::new('id')
            ->setLabel(t('label.shared_block_usages', [], 'ui'))
            ->formatValue(fn ($value, ?SharedBlock $sharedBlock): int => null === $sharedBlock ? 0 : $this->usageCount($sharedBlock))
            ->onlyOnIndex();

        // Composed from the creation form on, as a page is. row_attr markers read by ea-sortable.js, so a block can be dragged into a container of the same run
        yield CollectionField::new('blocks')
            ->setLabel(t('label.blocks', [], 'ui'))
            ->setColumns('col-12')
            ->setEntryType(BlockType::class)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(SharedBlockOwnerResolver::TYPE, $entity instanceof SharedBlock ? $entity->getId() : null))
            ->hideOnIndex();
    }

    // The slug is built once, here, from the name the editor typed - unique among shared blocks
    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof SharedBlock && null === $entityInstance->getSlug()) {
            $entityInstance->setSlug(UniqueSlug::build(
                $this->slugger,
                (string) $entityInstance->getName(),
                fn (string $candidate): bool => null !== $this->sharedBlockRepository->findOneBySlug($candidate)
            ));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    // A shared block still shown somewhere is kept, and the editor is told where: the pointers have to go first, page by page
    #[\Override]
    public function delete(AdminContext $context): KeyValueStore | Response
    {
        $sharedBlock = $context->getEntity()->getInstance();

        if ($sharedBlock instanceof SharedBlock) {
            $pointers = $this->sharedBlockUsage->findPointers((string) $sharedBlock->getSlug());

            if ([] !== $pointers) {
                $this->addFlash('danger', $this->translator->trans('flash.shared_block_used_not_deleted', [
                    '%count%' => count($pointers),
                    '%locations%' => $this->locations($pointers),
                ], 'ui'));

                return $this->redirect($this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl());
            }
        }

        return parent::delete($context);
    }

    // The language tabs above the edit form, shared with every screen that has a language of its own (see ContentLocaleScreen) - nothing at all on a site declaring a single one
    #[\Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $sharedBlock = $responseParameters->get('entity')?->getInstance();

        if (Crud::PAGE_EDIT === $responseParameters->get('pageName') && $sharedBlock instanceof SharedBlock && null !== $sharedBlock->getId()) {
            $this->contentLocaleScreen->addParameters($responseParameters, self::class, $sharedBlock->getId(), $this->contentTranslator->getTranslatableLocales(), $this->contentLocale());
        }

        return parent::configureResponseParameters($responseParameters);
    }

    // The language the screen is opened on, when it is not the one the site is written in
    private function contentLocale(): ?string
    {
        return $this->contentLocaleScreen->locale($this->contentTranslator->getTranslatableLocales());
    }

    private function usageCount(SharedBlock $sharedBlock): int
    {
        $this->usages ??= $this->sharedBlockUsage->countBySlug();

        return $this->usages[(string) $sharedBlock->getSlug()] ?? 0;
    }

    // Where the pointers sit, as their owners name it ("Page « Contact »"), read on the top block when a pointer sits inside a container
    /** @param Block[] $pointers */
    private function locations(array $pointers): string
    {
        $tops = [];
        foreach ($pointers as $pointer) {
            $top = $pointer;
            while (null !== $top->getParentBlock()) {
                $top = $top->getParentBlock();
            }
            $tops[(int) $pointer->getId()] = $top;
        }

        $resolved = $this->blockLocationRegistry->getLocations(array_values($tops));

        $labels = [];
        foreach ($tops as $top) {
            $labels[] = $resolved[$top->getId()]['label'] ?? $this->translator->trans('label.shared_block_unknown_location', [], 'ui');
        }

        return implode(', ', array_unique($labels));
    }
}
