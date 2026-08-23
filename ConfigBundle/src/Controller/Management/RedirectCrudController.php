<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use Doctrine\DBAL\Connection;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class RedirectCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Connection $connection,
        private readonly TableExporter $tableExporter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Redirect::class;
    }

    // The path arrives prefilled when the admin comes from the broken-links screen (see NotFoundCrudController), where it was already established that this very url is being asked for and answers nothing - retyping it is the one way left to get it wrong
    #[\Override]
    public function createEntity(string $entityFqcn): Redirect
    {
        $redirect = new Redirect();

        $fromPath = $this->getContext()?->getRequest()->query->get('fromPath');
        if (\is_string($fromPath) && '' !== $fromPath) {
            $redirect->setFromPath($fromPath);
        }

        return $redirect;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),

            TextField::new('fromPath')
                ->setLabel(t('label.from_path', [], 'config'))
                ->setHelp(t('label.from_path_help', [], 'config'))
                ->setRequired(true),

            // No longer required at the form level: a "gone" row has nothing to redirect to. Redirect::$toUrl carries the conditional constraint that still enforces it for every other row
            TextField::new('toUrl')
                ->setLabel(t('label.to_url', [], 'config'))
                ->setHelp(t('label.to_url_help', [], 'config'))
                ->setRequired(false),

            BooleanField::new('permanent')
                ->setLabel(t('label.permanent', [], 'config'))
                ->setHelp(t('label.permanent_help', [], 'config')),

            BooleanField::new('gone')
                ->setLabel(t('label.gone', [], 'config'))
                ->setHelp(t('label.gone_help', [], 'config')),
        ];
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $exportGroup = ActionGroup::new('export', t('label.export', [], 'config'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_NEW, $cancelAction)
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
            ->setPermission(Action::NEW, $this->configService->get('site-role-editor'))
            ->setPermission(Action::EDIT, $this->configService->get('site-role-editor'))
            ->setPermission(Action::DELETE, $this->configService->get('site-role-admin'))
            ->setPermission('exportSql', 'ROLE_SUPER_ADMIN')
            ->setPermission('exportCsv', $this->configService->get('site-role-admin'))
            ->setPermission('exportJson', 'ROLE_SUPER_ADMIN')
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->overrideTemplate('crud/index', '@c975LConfig/management/redirect_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LConfig/management/redirect_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LConfig/management/redirect_crud_new.html.twig')
        ;
    }

    // "gone" is worth a filter of its own: the rows a satellite bundle writes when it removes a page are all of that kind (see GalleryBundle, one per deleted media), and they would otherwise bury the handful of redirects an admin actually maintains by hand
    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('fromPath')
            ->add('toUrl')
            ->add(BooleanFilter::new('gone')->setLabel(t('label.gone', [], 'config')))
        ;
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Sql, 'site_redirect', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Csv, 'site_redirect', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Json, 'site_redirect', $this->fetchExportRows());
    }

    private function fetchExportRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `site_redirect` ORDER BY `id`');
    }
}
