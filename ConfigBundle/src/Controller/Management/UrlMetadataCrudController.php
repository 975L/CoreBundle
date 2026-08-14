<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\OgImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// Where the listings of a site are given their title and their summary - the pages no entity carries, which had nowhere to say anything (see UrlMetadata)
class UrlMetadataCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UrlMetadata::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),

            // Never editable: the path identifies the url this row describes, and the rows are created from what the bundles declare. Changing it by hand would move a description onto another url, or onto none
            TextField::new('path')
                ->setLabel(t('label.url_metadata_path', [], 'config'))
                ->setHelp(t('label.url_metadata_path_help', [], 'config'))
                ->setFormTypeOption('disabled', true),

            TextField::new('title')
                ->setLabel(t('label.url_metadata_title', [], 'config'))
                ->setHelp(t('label.url_metadata_title_help', [], 'config'))
                ->setRequired(false),

            // Same field, same label and same help text as a Page's own summary (see SiteBundle's PageCrudController): it feeds the same two tags, and the content quality health check names it by this very label when it reports one missing
            TextareaField::new('summarySocialNetwork')
                ->setLabel(t('label.summary_social_network', [], 'config'))
                ->setHelp(t('label.url_metadata_summary_help', [], 'config'))
                ->setRequired(false),

            // TextField and not AssociationField: a Media has no __toString(), and an association field would try to print one to label its choices. Same call SiteBundle's PageCrudController makes for a Page's own share image, and for the same reason - the field is the upload widget OgImageType draws, not a picker among existing rows
            TextField::new('ogImage')
                ->setLabel(t('label.url_metadata_og_image', [], 'config'))
                ->setHelp(t('label.url_metadata_og_image_help', [], 'config'))
                ->setFormType(OgImageType::class)
                ->setFormTypeOption('required', false)
                // On the write screens alone: the index lists the paths and what they say, and DETAIL is disabled anyway
                ->onlyOnForms(),
        ];
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        // Lets the admin back out of an edit without saving - mirrors EasyAdmin's own built-in actions, same as RedirectCrudController does
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $this->configService->get('site-role-editor'))
            ->setPermission(Action::EDIT, $this->configService->get('site-role-editor'))
            ->setPermission(Action::DELETE, $this->configService->get('site-role-admin'))
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
            // No row is ever added by hand: the paths come from the bundles that serve them (see UrlMetadataProviderInterface, and the c975l:url-metadata:sync command that lists them here). A path typed one slash apart would describe an url that does not exist, and nothing would say so. Deleting stays, for a row whose url is gone for good
            ->disable(Action::NEW)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->setDefaultSort(['path' => 'ASC'])
            ->overrideTemplate('crud/index', '@c975LConfig/management/url_metadata_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LConfig/management/url_metadata_crud_edit.html.twig')
        ;
    }

    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('path')
            ->add('title')
        ;
    }
}
