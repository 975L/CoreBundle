<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\ImageClassChoiceType;
use c975L\UiBundle\Form\MediaUsagesType;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use c975L\UiBundle\Service\MediaDimensionsFiller;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Cross-bundle media library: browses every c975L\UiBundle\Entity\Media row regardless of how it is attached (Block, Page og-image, site-wide role...) and shows where each one is used, via MediaUsageRegistry (fed by any bundle implementing MediaUsageProviderInterface). Site-wide role graphics (favicon, logo, error-image...) stay read-only here - they keep being managed in SiteGraphicCrudController, which enforces the one-row-per-singleton-role rule and its own alerts.
class MediaCrudController extends AbstractCrudController
{
    // No ConfigBundle dependency here (ConfigBundle already depends on UiBundle, so UiBundle must stay standalone) - apps wanting the same dynamic role as other c975L CRUDs can override this controller
    private const ROLE_NEEDED = 'ROLE_ADMIN';

    // Kept in line with php.ini's upload_max_filesize/post_max_size - MediaUploadType (the Block-attached upload form) has no such constraint of its own and simply relies on those same ini limits
    private const MAX_FILE_SIZE = '100M';

    public function __construct(
        private readonly MediaUsageRegistry $mediaUsageRegistry,
        private readonly TranslatorInterface $translator,
        private readonly MediaDimensionsFiller $mediaDimensionsFiller,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Media) {
            $this->mediaDimensionsFiller->fillIfBlank($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    // Same protection as MediaUploadType's own POST_SUBMIT listener: this form exposes the very same width/height fields, so saving a row whose inputs were rendered blank would erase the size auto-detected on upload
    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Media) {
            $this->mediaDimensionsFiller->fillIfBlank($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.media', [], 'ui'))
            ->setEntityLabelInPlural(t('label.media_library', [], 'ui'))
            ->setEntityPermission(self::ROLE_NEEDED)
            ->setDefaultSort(['id' => 'DESC'])
            ->overrideTemplate('crud/index', '@c975LUi/management/media_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LUi/management/media_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LUi/management/media_crud_new.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', t('action.cancel', domain: 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            // Detail isn't added to PAGE_INDEX by default - needed here as the fallback default action (entity.defaultActionUrl in media_index.html.twig) for role-set rows, since those hide Edit/Delete below and would otherwise have no action to link their gallery thumbnail to. Kept, unlike other CRUDs' redundant view page - it's the only click target a read-only role-set row has
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->setPermission(Action::INDEX, self::ROLE_NEEDED)
            ->setPermission(Action::EDIT, self::ROLE_NEEDED)
            ->setPermission(Action::DELETE, self::ROLE_NEEDED)
            ->setPermission(Action::DETAIL, self::ROLE_NEEDED)
            // Site-wide graphics (role set) are only editable from SiteGraphicCrudController
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (Media $media): bool => null === $media->getRole()),
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (Media $media): bool => null === $media->getRole()),
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.detail', [], 'EasyAdminBundle'),
            ))
            // Creating a Media with no Block (e.g. for a bundle showcase) is reserved to super admins - regular admins keep adding media the normal way, through a Block's own form
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // Shown on Detail (role-set rows, whose Edit action is hidden below), Edit and New - the index is a flat thumbnail gallery (see media_index.html.twig), not a table of fields, so this stays off it either way. Detail uses formatValue()+templatePath (EasyAdmin's normal read-only rendering); New/Edit need setFormType() instead - formatValue()/templatePath are never applied to New/Edit forms, which otherwise fall back to rendering the raw "id" as an editable number input. On New, MediaUsagesType simply has nothing to show yet (no id).
            Field::new('id')
                ->setLabel(t('label.used_in', [], 'ui'))
                ->formatValue(fn ($value, Media $media): array => $this->mediaUsageRegistry
                    ->getUsages([$media])[$media->getId()] ?? [])
                ->setTemplatePath('@c975LUi/management/media_usages.html.twig')
                ->setFormType(MediaUsagesType::class)
                ->setRequired(false)
                ->hideOnIndex(),

            Field::new('file')
                ->setLabel(t('label.file', [], 'ui'))
                ->setFormType(VichImageType::class)
                ->setFormTypeOptions([
                    'required' => false,
                    'allow_delete' => true,
                    'download_uri' => true,
                    'asset_helper' => true,
                    // VichImageType defaults image_uri to the uploaded file itself, which is fine for actual images but renders a dead <img> for a PDF - swap it for the .webp thumbnail (see VichPdfThumbnailListener) when one exists, same convention already used by DocumentExtension::getThumbnailPath() for the public block rendering
                    'image_uri' => function (Media $media, ?string $originalUri): ?string {
                        if (null === $originalUri || 'application/pdf' !== $media->getMimeType()) {
                            return $originalUri;
                        }

                        $webpPath = VichPdfThumbnailListener::toWebpPath($originalUri);

                        return is_file($this->projectDir . '/public/' . $webpPath) ? $webpPath : $originalUri;
                    },
                    // Without this, the "delete file" checkbox label falls back to whatever domain EasyAdmin resolves by default for nested form widgets, which doesn't carry this key here - same fix already applied in MediaUploadType for the Block forms
                    'delete_label_translation_domain' => 'messages',
                    'constraints' => [
                        new FileConstraint(maxSize: self::MAX_FILE_SIZE),
                    ],
                ])
                ->onlyOnForms(),

            TextField::new('alt')
                ->setLabel(t('label.alt_text', [], 'ui'))
                ->hideOnIndex(),

            // Slugified by UiMediaNamer into the stored/physical filename (e.g. "cv-lilouan-xxx.pdf") instead of the default "block-{kind}-{id}" - only takes effect on the next upload, editing it here doesn't rename a file already on disk. Distinct from "label" below (a display caption, not filesystem-safe).
            TextField::new('name')
                ->setLabel(t('label.file_name', [], 'ui'))
                ->setHelp(t('label.file_name_help', [], 'ui'))
                ->hideOnIndex(),

            TextField::new('label')
                ->setLabel(t('label.caption', [], 'ui'))
                ->hideOnIndex(),

            // Same fields as the caption/positioning group in MediaUploadType (the Block form), kept in parity so editing a Media from the library isn't missing anything a Block's own inline media form offers
            TextField::new('width')
                ->setLabel(t('label.width', [], 'ui'))
                ->setHelp(t('label.width_help', [], 'ui'))
                ->hideOnIndex(),

            TextField::new('height')
                ->setLabel(t('label.height', [], 'ui'))
                ->setHelp(t('label.height_help', [], 'ui'))
                ->hideOnIndex(),

            BooleanField::new('above')
                ->setLabel(t('label.caption_above', [], 'ui'))
                ->hideOnIndex(),

            // Native ChoiceField (not Field/TextField): EasyAdmin's TextConfigurator throws on any non-string value regardless of a custom setFormType(), and a plain Field::new() on this json-typed column gets auto-promoted to ArrayField, whose default CollectionType options (entry_type, allow_add...) collide with ImageClassChoiceType (a plain ChoiceType). ChoiceField natively supports multi-valued/array-backed choices, so we replicate its options here instead of reusing the form type directly. Left non-expanded (default) so it renders as the same removable-tags autocomplete widget used for Serie and for block classes.
            ChoiceField::new('cssClasses')
                ->setLabel(t('label.css_classes', [], 'ui'))
                ->setTranslatableChoices(array_combine(
                    array_values(ImageClassChoiceType::CHOICES),
                    array_map(static fn (string $labelKey) => t($labelKey, [], 'ui'), array_keys(ImageClassChoiceType::CHOICES))
                ))
                ->allowMultipleChoices()
                ->onlyOnForms(),

            TextField::new('credits')
                ->setLabel(t('label.credits', [], 'ui'))
                ->hideOnIndex(),

            BooleanField::new('rightsReserved')
                ->setLabel(t('label.rights_reserved', [], 'ui'))
                ->hideOnIndex(),
        ];
    }
}
