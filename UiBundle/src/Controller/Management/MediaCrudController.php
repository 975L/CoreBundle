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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\ImageClassChoiceType;
use c975L\UiBundle\Form\MediaUsagesType;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use c975L\UiBundle\Service\MediaDimensionsFiller;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Cross-bundle media library: browses every c975L\UiBundle\Entity\Media row regardless of how it is attached (Block, Page og-image, site-wide role...) and shows where each one is used, via MediaUsageRegistry (fed by any bundle implementing MediaUsageProviderInterface). Site-wide role graphics (favicon, logo, error-image...) stay read-only here - they keep being managed in SiteGraphicCrudController, which enforces the one-row-per-singleton-role rule and its own alerts.
class MediaCrudController extends AbstractCrudController
{
    // No ConfigBundle dependency here (ConfigBundle already depends on UiBundle, so UiBundle must stay standalone) - apps wanting the same dynamic role as other c975L CRUDs can override this controller
    private const string ROLE_NEEDED = 'ROLE_ADMIN';

    // Kept in line with php.ini's upload_max_filesize/post_max_size - MediaUploadType (the Block-attached upload form) has no such constraint of its own and simply relies on those same ini limits
    private const string MAX_FILE_SIZE = '100M';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MediaDimensionsFiller $mediaDimensionsFiller,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    // Hands the gallery template the edit url of each role-carrying row (see media_index.html.twig) - those are read-only here, so their thumbnail has to open the screen that does edit them, SiteGraphicCrudController
    #[\Override]
    public function index(AdminContext $context): KeyValueStore | Response
    {
        $responseParameters = parent::index($context);

        if ($responseParameters instanceof KeyValueStore) {
            $responseParameters->set('site_graphic_urls', $this->siteGraphicUrls($responseParameters->get('entities')));
        }

        return $responseParameters;
    }

    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Media) {
            $this->mediaDimensionsFiller->fillIfBlank($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    // Same protection as MediaUploadType's own POST_SUBMIT listener: this form exposes the very same width/height fields, so saving a row whose inputs were rendered blank would erase the size auto-detected on upload
    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Media) {
            $this->mediaDimensionsFiller->fillIfBlank($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    #[\Override]
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

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', t('action.cancel', domain: 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->setPermission(Action::INDEX, self::ROLE_NEEDED)
            ->setPermission(Action::EDIT, self::ROLE_NEEDED)
            ->setPermission(Action::DELETE, self::ROLE_NEEDED)
            // Site-wide graphics (role set) are only editable from SiteGraphicCrudController
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (Media $media): bool => null === $media->getRole()),
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (Media $media): bool => null === $media->getRole()),
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            // Creating a Media with no Block (e.g. for a bundle showcase) is reserved to super admins - regular admins keep adding media the normal way, through a Block's own form
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            // Same reason as in SiteGraphicCrudController: detail adds no information beyond what edit already shows, and it doesn't even display the file itself (only forms do). Every gallery thumbnail now opens a form - Edit here, or SiteGraphicCrudController's own for a role-carrying row (see index())
            ->disable(Action::DETAIL)
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            // Forms only: the index is a flat thumbnail gallery (see media_index.html.twig), not a table of fields, and Detail is disabled above. setFormType() is what renders it - EasyAdmin's own formatValue()/templatePath pair is never applied to New/Edit forms, which otherwise fall back to rendering the raw "id" as an editable number input. On New, MediaUsagesType simply has nothing to show yet (no id).
            Field::new('id')
                ->setLabel(t('label.used_in', [], 'ui'))
                ->setFormType(MediaUsagesType::class)
                ->setRequired(false)
                ->onlyOnForms(),

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

    // The url editing each role-carrying media (favicon, logo, error-image...) in SiteGraphicCrudController, keyed by media id - empty on a page holding only block medias, which keep their own Edit action here
    private function siteGraphicUrls(EntityCollection $entities): array
    {
        $urls = [];

        // The very permission SiteGraphicCrudController gates itself with: without it the link would only ever land on a 403, so the thumbnail is left with no url at all and stops being a link (see media_index.html.twig)
        if (!$this->security->isGranted((string) $this->configService->get('site-role-editor'))) {
            return $urls;
        }

        foreach ($entities as $entity) {
            $media = $entity->getInstance();

            if ($media instanceof Media && null !== $media->getRole()) {
                $urls[$media->getId()] = $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(SiteGraphicCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($media->getId())
                    ->generateUrl();
            }
        }

        return $urls;
    }
}
